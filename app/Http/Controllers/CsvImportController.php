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
            $fileContents = file_get_contents($url);
            $fileName = 'images/' . uniqid() . '.png';

            Storage::disk('public')->put($fileName, $fileContents);

            return asset('storage/' . $fileName); // Returns a usable URL
        } catch (\Exception $e) {
            return null; // Return null if download fails
        }
    }


    public function importCsv(Request $request)
    {
        $request->validate([
            'question_file' => ['required', 'file', 'mimes:csv,txt'], // Validate file
            'subject_id' => ['required', 'exists:subjects,id'],       // Validate subject_id exists in the database
        ]);

        $filePath = $request->file('question_file');
        $subjectId = $request->input('subject_id');

        // Open and read the CSV file
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return response()->json(['error' => 'CSV file not found or not readable'], 400);
        }

        $fileHandle = fopen($filePath->getRealPath(), 'r');
        $header = fgetcsv($fileHandle); // Read header row

        DB::beginTransaction();

        try {
            while (($row = fgetcsv($fileHandle)) !== false) {
                [
                    $serial, $year, $questionText, $image, $optionA, $optionB,
                    $optionC, $optionD, $optionE, $correctOption, $solution, $sectionName,
                    $chapterNumber, $topicName, $objectiveName
                ] = $row;
        
                // Fetch Section
                $section = Section::where('subject_id', $subjectId)
                    ->first();
        
                if (!$section) {
                    throw new \Exception("Section not found: {$sectionName}");
                }
        
                // Fetch Chapter
                $chapter = Chapter::where('subject_id', $subjectId)
                    ->first();
        
                if (!$chapter) {
                    throw new \Exception("Chapter not found: {$chapterNumber}");
                }
        
                // Fetch Topic
                $topic = Topic::where('subject_id', $subjectId)
                    ->first();
        
                if (!$topic) {
                    throw new \Exception("Topic not found: {$topicName}");
                }
        
                // Fetch Objective
                $objective = Objective::where('id', $topic->id)->first();
        
                if (!$objective) {
                    throw new \Exception("Objective not found: {$objectiveName}");
                }
        
                dd($this->storeGoogleDriveImage($this->convertGoogleDriveUrl($image)));
                // Create Question
                $question = Question::create([
                    'year' => $year,
                    'question' => $questionText,
                    'image_url' => $this->storeGoogleDriveImage($this->convertGoogleDriveUrl($image)),
                    'solution' => $solution,
                    'section_id' => $section->id,
                    'chapter_id' => $chapter->id,
                    'topic_id' => $topic->id,
                    'objective_id' => $objective->id,
                    'subject_id' => $subjectId,
                ]);
        
                // Insert Options
                $options = [
                    ['value' => $optionA, 'is_correct' => $correctOption === 'A'],
                    ['value' => $optionB, 'is_correct' => $correctOption === 'B'],
                    ['value' => $optionC, 'is_correct' => $correctOption === 'C'],
                    ['value' => $optionD, 'is_correct' => $correctOption === 'D'],
                    ['value' => $optionE, 'is_correct' => $correctOption === 'E'],
                ];
        
                foreach ($options as $option) {
                    DB::table('question_options')->insert([
                        'question_id' => $question->id,
                        'value' => $option['value'],
                        'is_correct' => $option['is_correct'],
                    ]);
                }
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
}
