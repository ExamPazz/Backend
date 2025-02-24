<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use Illuminate\Http\Request;
use App\Models\Section;
use App\Models\Chapter;
use App\Models\Topic;
use App\Models\Objective;
use App\Models\Question;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;

class CsvImportController extends Controller
{
    

    public function importQuestions(Request $request)
    {
        $request->validate([
            'subject_name' => ['required', 'unique:subjects,name'],
            'question_file' => ['required', 'file', 'mimes:csv,xlsx']
        ]);

        $file = $request->file('question_file');
        $subject_name = $request->input('subject_name');

        try {
            // Load the spreadsheet
            $spreadsheet = IOFactory::load($file->getRealPath());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            // Remove header row
            $header = array_shift($rows);

            DB::beginTransaction();

            $subject = Subject::query()->create([
                'name' => ucwords($subject_name)
            ]);

            foreach ($rows as $row) {
                // Skip empty rows
                if (empty(array_filter($row))) {
                    continue;
                }

                // Map spreadsheet columns to variables with null coalescing
                [
                    $serial, $year, $questionText, $optionA, $optionB,
                    $optionC, $optionD, $correctAnswerExplanation, $solution,
                    $sectionName, $chapterNumber, $topicName, $objectiveName, $code
                ] = array_pad($row, 14, null);

                // Validate essential fields
                $validator = Validator::make(
                    [
                        'question' => $questionText,
                        'optionA' => $optionA,
                        'optionB' => $optionB,
                        'section' => $sectionName,
                        'chapter' => $chapterNumber,
                        'topic' => $topicName,
                        'objective' => $objectiveName
                    ],
                    [
                        'question' => 'required|string',
                        'optionA' => 'required|string',
                        'optionB' => 'required|string',
                        'section' => 'required|string',
                        'chapter' => 'required|string',
                        'topic' => 'required|string',
                        'objective' => 'required|string'
                    ]
                );

                if ($validator->fails()) {
                    continue;
                }

                // Sanitize and validate inputs
                $year = filter_var($year, FILTER_VALIDATE_INT) ? (int) $year : null;
                $sectionName = substr(trim($sectionName), 0, 191);
                $chapterNumber = substr(trim($chapterNumber), 0, 191);
                $topicName = substr(trim($topicName), 0, 191);
                $objectiveName = substr(trim($objectiveName), 0, 191);

                // Handle Section with error checking
                $section = Section::query()->firstOrCreate(
                    ['code' => $sectionName],
                    ['subject_id' => $subject->id]
                );

                // Handle Chapter with error checking
                $chapter = Chapter::query()->firstOrCreate(
                    ['code' => $chapterNumber],
                    [
                        'subject_id' => $subject->id,
                        'body' => $chapterNumber
                    ]
                );

                // Handle Topic with error checking
                $topic = Topic::query()->firstOrCreate(
                    ['code' => $topicName],
                    [
                        'subject_id' => $subject->id,
                        'body' => $topicName
                    ]
                );

                // Handle Objective with error checking
                $objective = Objective::query()->firstOrCreate(
                    ['code' => $objectiveName],
                    [
                        'topic_id' => $topic->id,
                        'body' => $objectiveName
                    ]
                );

                // Create Question with error checking
                $question = Question::query()->create([
                    'year' => $year,
                    'question' => $questionText,
                    'image_url' => null,
                    'solution' => $solution,
                    'section_id' => $section->id,
                    'subject_id' => $subject->id,
                    'chapter_id' => $chapter->id,
                    'topic_id' => $topic->id,
                    'objective_id' => $objective->id,
                ]);

                // Prepare options array
                $options = collect([
                    ['value' => $optionA, 'is_correct' => false],
                    ['value' => $optionB, 'is_correct' => false],
                    ['value' => $optionC, 'is_correct' => false],
                    ['value' => $optionD, 'is_correct' => false],
                ])->map(function ($option) use ($question, $correctAnswerExplanation) {
                    return [
                        'question_id' => $question->id,
                        'value' => $option['value'],
                        'is_correct' => !empty($correctAnswerExplanation) &&
                            str_contains($correctAnswerExplanation, $option['value']),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                })->filter(function ($option) {
                    return !empty($option['value']);
                })->toArray();

                if (!empty($options)) {
                    DB::table('question_options')->insert($options);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'File imported successfully!',
                'subject_id' => $subject->id
            ]);

        } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Invalid file format: '.$e->getMessage()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Import failed: '.$e->getMessage()], 500);
        }

    }

    private function convertGoogleDriveUrl($url)
    {
        if (preg_match('/drive\.google\.com\/file\/d\/([^\/]+)\//', $url, $matches)) {
            return 'https://drive.google.com/uc?export=download&id=' . $matches[1];
        }

        if (preg_match('/drive\.google\.com\/open\?id=([^&]+)/', $url, $matches)) {
            return 'https://drive.google.com/uc?export=download&id=' . $matches[1];
        }

        return $url;
    }

    private function storeGoogleDriveImage($url)
    {
        try {
            // Convert Google Drive link to direct URL
            $directUrl = $this->convertGoogleDriveUrl($url);

            if (!$directUrl) {
                throw new \Exception('Invalid Google Drive URL');
            }

            // Download file contents with SSL verification disabled
            $response = Http::withoutVerifying()->get($directUrl);

            // Generate unique file name
            $fileName = 'images/' . uniqid() . '.png';

            // Store the image in Laravel's public storage
            Storage::disk('public')->put($fileName, $response->body());

            // Return the accessible URL
            return asset('storage/' . $fileName);
        } catch (\Exception $e) {
            dd("Skipping image due to error: " . $e->getMessage());
            return null;
        }
    }

    public function importCsv(Request $request)
    {
        set_time_limit(0); // Removes time limit
        
        $request->validate([
            'question_file' => ['required', 'file', 'mimes:csv,txt,xlsx'],
            'subject_id' => ['required', 'exists:subjects,id'],
        ]);
    
        $filePath = $request->file('question_file');
        $subjectId = $request->input('subject_id');
    
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return response()->json(['error' => 'CSV file not found or not readable'], 400);
        }
    
        $batchSize = 100;
        $processedRows = 0;
    
        DB::beginTransaction();
    
        try {
            $sections = $chapters = $topics = $objectives = [];
    
            $rows = LazyCollection::make(function () use ($filePath) {
                $handle = fopen($filePath->getRealPath(), 'r');
                fgetcsv($handle); // Skip header
                while ($row = fgetcsv($handle)) {
                    yield $row;
                }
                fclose($handle);
            });
    
            $rows->chunk($batchSize)->each(function ($chunk) use (&$sections, &$chapters, &$topics, &$objectives, $subjectId, &$processedRows) {
                $questionData = [];
    
                foreach ($chunk as $row) {
                    [
                        $serial, $year, $questionText, $image, $optionA, $optionB,
                        $optionC, $optionD, $optionE, $correctOption, $solution,
                        $sectionName, $chapterNumber, $topicName, $objectiveName
                    ] = $row;
    
                    // Validate and create relationships if not found in cached collections
                    $sectionId = $sections[$sectionName] ??= Section::firstOrCreate([
                        'code' => $sectionName, 'subject_id' => $subjectId
                    ])->id;
    
                    $chapterId = $chapters[$chapterNumber] ??= Chapter::firstOrCreate([
                        'code' => $chapterNumber, 'subject_id' => $subjectId
                    ])->id;
    
                    $topicId = $topics[$topicName] ??= Topic::firstOrCreate([
                        'code' => $topicName
                    ])->id;
    
                    $objectiveId = $objectives[$objectiveName] ??= Objective::firstOrCreate([
                        'code' => $objectiveName,
                        'topic_id' => $topicId,  // Add this line
                    ])->id;                    
    
                    $imageUrl = (!empty($image) && str_contains($image, 'drive.google.com'))
                        ? $this->storeGoogleDriveImage($this->convertGoogleDriveUrl($image))
                        : null;
    
                    $questionData[] = [
                        'question' => [
                            'year' => $year,
                            'question' => $questionText,
                            'image_url' => $imageUrl,
                            'solution' => $solution,
                            'section_id' => $sectionId,
                            'chapter_id' => $chapterId,
                            'topic_id' => $topicId,
                            'objective_id' => $objectiveId,
                            'subject_id' => $subjectId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                        'options' => [
                            'values' => [$optionA, $optionB, $optionC, $optionD, $optionE],
                            'correct' => $correctOption
                        ]
                    ];
                }
    
                // Insert questions and options
                foreach (array_chunk($questionData, 50) as $questionChunk) {
                    $questions = array_column($questionChunk, 'question');
                    $firstId = DB::table('questions')->insertGetId(array_shift($questions));
    
                    if ($questions) {
                        DB::table('questions')->insert($questions);
                    }
    
                    $currentId = $firstId;
                    $optionsData = [];
    
                    foreach ($questionChunk as $data) {
                        foreach (['A', 'B', 'C', 'D', 'E'] as $i => $label) {
                            $option = $data['options']['values'][$i] ?? null;
                            if (empty($option)) continue;
    
                            $optionValue = str_contains($option, 'drive.google.com')
                                ? $this->storeGoogleDriveImage($this->convertGoogleDriveUrl($option))
                                : $option;
    
                            $optionsData[] = [
                                'question_id' => $currentId,
                                'value' => $optionValue,
                                'is_correct' => $data['options']['correct'] === $label,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }
                        $currentId++;
                    }
    
                    if ($optionsData) {
                        foreach (array_chunk($optionsData, 50) as $chunk) {
                            DB::table('question_options')->insert($chunk);
                        }
                    }
                }
    
                $processedRows += count($questionData);
            });
    
            DB::commit();
    
            return response()->json([
                'message' => 'CSV data imported successfully!',
                'processed_rows' => $processedRows
            ]);
    
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to import CSV data: ' . $e->getMessage()
            ], 500);
        }
    }

}
