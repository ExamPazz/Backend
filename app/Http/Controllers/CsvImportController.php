<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Section;
use App\Models\Chapter;
use App\Models\Topic;
use App\Models\Objective;
use App\Models\Question;
use Illuminate\Support\Facades\DB;

class CsvImportController extends Controller
{
    public function importCsv(Request $request)
    {
        // Define file path (update this based on actual path or input)
        $filePath = 'C:\\Users\\olanm\\Downloads\\2017 Data Bank.csv';
        
        // Open and read the CSV file
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return response()->json(['error' => 'CSV file not found or not readable'], 400);
        }

        $fileHandle = fopen($filePath, 'r');
        $header = fgetcsv($fileHandle); // Read header row

        // Start database transaction
        DB::beginTransaction();

        // dd($row = fgetcsv($fileHandle));


        try {
            while (($row = fgetcsv($fileHandle)) !== false) {
                // Map CSV columns to variables
                [
                    $row, $serial, $year, $questionText, $image, $optionA, $optionB, 
                    $optionC, $optionD, $correctOption, $solution, $sectionName, 
                    $chapterNumber, $topicName, $objectiveName
                ] = $row;

                // dd($row[1]);
                if (!empty($row[0])) {
                    dd($questionText);
                }

                // Skip rows with empty required fields
                if (
                    empty($sectionName) || 
                    empty($chapterNumber) || 
                    empty($topicName) || 
                    empty($objectiveName)
                ) {
                    dd("Skipping row due to empty required fields: " . json_encode($row));
                    continue;
                }

                // Validate and sanitize inputs
                $year = is_numeric($year) ? $year : null;
                if (is_null($year)) {
                    dd("Skipping row due to invalid year: " . json_encode($row));
                    continue;
                }

                $sectionName = substr(trim($sectionName), 0, 191);
                $chapterNumber = substr(trim($chapterNumber), 0, 191);
                $topicName = substr(trim($topicName), 0, 191);
                $objectiveName = substr(trim($objectiveName), 0, 191);

                // Handle Section
                $section = Section::create(['id' => $sectionName]);

                // Handle Chapter

                $chapter = Chapter::firstOrCreate([
                    'id' => $chapterNumber,
                    'body' => 'Chapter Description Here', // Update as needed
                ]);


                // Handle Topic
                $topic = Topic::create([
                    'body' => $topicName
                ]);

                // Handle Objective
                $objective = Objective::firstOrCreate([
                    'id' => $objectiveName,
                    'body' => 'Objective Description Here'
                ]);

                // Insert Question
                $question = new Question();
                $question->year = $year;
                $question->question = $questionText;
                $question->image_url = $image;
                $question->option_a = $optionA;
                $question->option_b = $optionB;
                $question->option_c = $optionC;
                $question->option_d = $optionD;
                $question->solution = $solution;

                // Link to related records
                $question->section_id = $section->id;
                $question->chapter_id = $chapterNumber;
                $question->topic_id = $topic->id;
                $question->objective_id = $objective->id;

                $question->save();
            }

            // Commit transaction
            DB::commit();
            fclose($fileHandle);

            return response()->json(['message' => 'CSV data imported successfully!']);
        } catch (\Exception $e) {
            // Rollback on error
            DB::rollBack();
            fclose($fileHandle);
            // \Log::error('Failed to import CSV data: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to import CSV data: ' . $e->getMessage()], 500);
        }
    }
}
