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

    $fileHandle = fopen($filePath->getRealPath(), 'r');
    $header = fgetcsv($fileHandle); // Skip header row


    $batchSize = 500;
    $batch = [];

    DB::beginTransaction();

    try {
        while (($row = fgetcsv($fileHandle))) {
            [
                $serial, $year, $questionText, $image, $optionA, $optionB,
                $optionC, $optionD, $optionE, $correctOption, $solution,
                $sectionName, $chapterNumber, $topicName, $objectiveName
            ] = $row;
                // dd($sectionName);
            // Validate relationships using pre-fetched data
           // Fetch Section
    // Section
$section = Section::firstOrCreate(
    ['subject_id' => $subjectId],
    ['subject_id' => $subjectId, 'created_at' => now(), 'updated_at' => now()]
);

// Chapter
$chapter = Chapter::firstOrCreate(
    ['subject_id' => $subjectId, 'code' => $chapterNumber],
    ['subject_id' => $subjectId, 'code' => $chapterNumber, 'created_at' => now(), 'updated_at' => now()]
);

// Topic
$topic = Topic::firstOrCreate(
    ['code' => $topicName],
    ['code' => $topicName, 'created_at' => now(), 'updated_at' => now()]
);

// Objective
$objective = Objective::firstOrCreate(
    ['code' => $objectiveName, 'topic_id' => $topic->id],  // Search criteria
    [
        'code' => $objectiveName,
        'topic_id' => $topic->id,                          // Provide the required foreign key
        'created_at' => now(),
        'updated_at' => now(),
    ]
);




            if (!$section || !$chapter || !$topic || !$objective) {
                throw new \Exception("Invalid related data for: Section {$sectionName}, Chapter {$chapterNumber}, Topic {$topicName}, Objective {$objectiveName}");
            }

            // Process image
            $imageUrl = (!empty($image) && str_contains($image, 'drive.google.com'))
                ? $this->storeGoogleDriveImage($this->convertGoogleDriveUrl($image))
                : null;

            // Prepare question data
            $questionData = [
                'year' => $year,
                'question' => $questionText,
                'image_url' => $imageUrl,
                'solution' => $solution,
                'section_id' => $section->id,
                'chapter_id' => $chapter->id,
                'topic_id' => $topic->id,
                'objective_id' => $objective->id,
                'subject_id' => $subjectId,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $options = [$optionA, $optionB, $optionC, $optionD, $optionE];
            $optionLabels = ['A', 'B', 'C', 'D', 'E'];

            $batch[] = compact('questionData', 'options', 'correctOption', 'optionLabels');

            // Process batch of 400
            if (count($batch) >= $batchSize) {
                $this->processBatch($batch);
                $batch = [];
            }
        }

        // Process remaining batch if any
        if (!empty($batch)) {
            $this->processBatch($batch);
        }

        DB::commit();
        fclose($fileHandle);

        return response()->json(['message' => 'CSV data imported successfully!']);
    } catch (\Exception $e) {
        DB::rollBack();
        fclose($fileHandle);

        return response()->json([
            'error' => 'Failed to import CSV data: ' . $e->getMessage()
        ], 500);
    }
}

/**
 * Processes a batch of questions and options.
 */
private function processBatch(array $batch)
{
    $questionInserts = [];
    $optionInserts = [];

    foreach ($batch as $item) {
        $questionInserts[] = $item['questionData'];
    }

    // Insert questions and retrieve their IDs
    Question::insert($questionInserts);
    $insertedQuestions = Question::latest()->take(count($questionInserts))->get();

    foreach ($insertedQuestions as $index => $question) {
        $options = $batch[$index]['options'];
        $correctOption = $batch[$index]['correctOption'];
        $optionLabels = $batch[$index]['optionLabels'];

        foreach ($options as $i => $option) {
            $optionValue = (!empty($option) && str_contains($option, 'drive.google.com'))
                ? $this->storeGoogleDriveImage($this->convertGoogleDriveUrl($option))
                : $option;

            $optionInserts[] = [
                'question_id' => $question->id,
                'value' => $optionValue,
                'is_correct' => $correctOption === $optionLabels[$i],
            ];
        }
    }

    // Insert options in bulk
    DB::table('question_options')->insert($optionInserts);
}

}
