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
        $request->validate([
            'question_file' => ['required', 'file', 'mimes:csv,txt,xlsx'],
            'subject_id' => ['required', 'exists:subjects,id'],
        ]);

        $filePath = $request->file('question_file');
        $subjectId = $request->input('subject_id');

        if (!file_exists($filePath) || !is_readable($filePath)) {
            return response()->json(['error' => 'CSV file not found or not readable'], 400);
        }

        // Pre-fetch and cache relationships
        $sections = Section::where('subject_id', $subjectId)->pluck('id', 'code');
        $chapters = Chapter::where('subject_id', $subjectId)->pluck('id', 'code');
        $topics = Topic::pluck('id', 'code');
        $objectives = Objective::pluck('id', 'code');

        $batchSize = 100;
        $processedRows = 0;

        DB::beginTransaction();

        try {
            $rows = LazyCollection::make(function () use ($filePath) {
                $handle = fopen($filePath->getRealPath(), 'r');
                fgetcsv($handle); // Skip header

                while ($row = fgetcsv($handle)) {
                    yield $row;
                }

                fclose($handle);
            });

            // Process in chunks
            $rows->chunk($batchSize)->each(function ($chunk) use (
                $subjectId,
                $sections,
                $chapters,
                $topics,
                $objectives,
                &$processedRows
            ) {
                $questionInserts = [];
                $optionInserts = [];
                $lastInsertId = null;

                foreach ($chunk as $row) {
                    [
                        $serial, $year, $questionText, $image, $optionA, $optionB,
                        $optionC, $optionD, $optionE, $correctOption, $solution,
                        $sectionName, $chapterNumber, $topicName, $objectiveName
                    ] = $row;

                    // Validate relationships using cached data
                    if (!isset($sections[$sectionName]) ||
                        !isset($chapters[$chapterNumber]) ||
                        !isset($topics[$topicName]) ||
                        !isset($objectives[$objectiveName])) {
                        \Illuminate\Support\Facades\Log::warning("Skipping row due to missing relationship", compact(
                            'sectionName',
                            'chapterNumber',
                            'topicName',
                            'objectiveName'
                        ));
                        continue;
                    }

                    // Process image if needed
                    $imageUrl = null;
                    if (!empty($image) && str_contains($image, 'drive.google.com')) {
                        $imageUrl = $this->storeGoogleDriveImage($this->convertGoogleDriveUrl($image));
                    }

                    // Remove UUID generation and just prepare the insert data
                    $questionInserts[] = [
                        'year' => $year,
                        'question' => $questionText,
                        'image_url' => $imageUrl,
                        'solution' => $solution,
                        'section_id' => $sections[$sectionName],
                        'chapter_id' => $chapters[$chapterNumber],
                        'topic_id' => $topics[$topicName],
                        'objective_id' => $objectives[$objectiveName],
                        'subject_id' => $subjectId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                // Bulk insert questions and get IDs
                if (!empty($questionInserts)) {
                    // Insert questions in chunks and get the first ID of each chunk
                    foreach (array_chunk($questionInserts, 50) as $questionChunk) {
                        $firstId = DB::table('questions')->insertGetId($questionChunk[0]);

                        // Insert the rest of the chunk
                        if (count($questionChunk) > 1) {
                            DB::table('questions')->insert(array_slice($questionChunk, 1));
                        }

                        // Calculate IDs for options based on the first ID
                        $currentId = $firstId;
                        foreach ($chunk as $row) { // Use original $chunk instead of $questionChunk
                            [
                                $serial, $year, $questionText, $image, $optionA, $optionB,
                                $optionC, $optionD, $optionE, $correctOption, $solution,
                                $sectionName, $chapterNumber, $topicName, $objectiveName
                            ] = $row;

                            // Prepare options for this question
                            $options = [$optionA, $optionB, $optionC, $optionD, $optionE];
                            $optionLabels = ['A', 'B', 'C', 'D', 'E'];

                            foreach ($options as $i => $option) {
                                if (empty($option)) continue;

                                $optionValue = str_contains($option, 'drive.google.com')
                                    ? $this->storeGoogleDriveImage($this->convertGoogleDriveUrl($option))
                                    : $option;

                                $optionInserts[] = [
                                    'question_id' => $currentId,
                                    'value' => $optionValue,
                                    'is_correct' => $correctOption === $optionLabels[$i],
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ];
                            }
                            $currentId++;
                        }
                    }

                    // Insert options in chunks
                    foreach (array_chunk($optionInserts, 50) as $optionChunk) {
                        DB::table('question_options')->insert($optionChunk);
                    }
                }

                $processedRows += count($chunk);
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
