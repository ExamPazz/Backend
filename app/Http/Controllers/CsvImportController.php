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

class CsvImportController extends Controller
{
    public function importCsv(Request $request)
    {
        $request->validate([
            'subject_name' => ['required', 'unique:subjects,name']
        ]);
        $filePath = $request->file('question_csv');
        $subject_name = $request->input('subject_name');

        // Open and read the CSV file
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return response()->json(['error' => 'CSV file not found or not readable'], 400);
        }

        $fileHandle = fopen($filePath->getRealPath(), 'r');
        $header = fgetcsv($fileHandle); // Read header row

        // Start database transaction
        DB::beginTransaction();

        // dd($row = fgetcsv($fileHandle));
        $subject = Subject::query()->create([
            'name' => ucwords($subject_name)
        ]);

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
                    dd("Skipping row due to empty required fields: ".json_encode($row));
                    continue;
                }

                // Validate and sanitize inputs
                $year = is_numeric($year) ? $year : null;
                if (is_null($year)) {
                    dd("Skipping row due to invalid year: ".json_encode($row));
                    continue;
                }

                $sectionName = substr(trim($sectionName), 0, 191);
                $chapterNumber = substr(trim($chapterNumber), 0, 191);
                $topicName = substr(trim($topicName), 0, 191);
                $objectiveName = substr(trim($objectiveName), 0, 191);


                // Handle Section
                $section = Section::query()->firstOrCreate(['code' => $sectionName], [
                    'subject_id' => $subject->id
                ]);


                // Handle Chapter

                $chapter = Chapter::firstOrCreate(['code' => $chapterNumber], [
                    'subject_id' => $subject->id,
                    'body' => 'Chapter Description Here', // Update as needed
                ]);


                // Handle Topic
                $topic = Topic::query()->firstOrCreate(['code' => $topicName], [
                    'subject_id' => $subject->id,
                    'body' => $topicName
                ]);

                // Handle Objective
                $objective = Objective::query()->firstOrCreate(['code' => $topicName], [
                    'topic_id' => $topic->id,
                    'body' => 'Objective Description Here'
                ]);

                // Insert Question
                $question = new Question();
                $question->year = $year;
                $question->question = $questionText;
                $question->image_url = $image;


                $question->solution = $solution;

                // Link to related records
                $question->section_id = $section->id;
                $question->subject_id = $subject->id;
                $question->chapter_id = $chapter->id;
                $question->topic_id = $topic->id;
                $question->objective_id = $objective->id;
                $question->save();

                $ques_arr = [
                    'A' => $optionA,
                    'B' => $optionB,
                    'C' => $optionC,
                    'D' => $optionD
                ];

                DB::table('question_options')->insert([
                    [
                        'question_id' => $question->id,
                        'value' => $ques_arr['A'],
                        'is_correct' => array_search($ques_arr['A'], $ques_arr) == $correctOption
                    ],
                    [
                        'question_id' => $question->id,
                        'value' => $ques_arr['B'],
                        'is_correct' => array_search($ques_arr['B'], $ques_arr) == $correctOption
                    ],
                    [
                        'question_id' => $question->id,
                        'value' => $ques_arr['C'],
                        'is_correct' => array_search($ques_arr['C'], $ques_arr) == $correctOption
                    ],
                    [
                        'question_id' => $question->id,
                        'value' => $ques_arr['D'],
                        'is_correct' => array_search($ques_arr['D'], $ques_arr) == $correctOption
                    ],
                ]);
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
            return response()->json(['error' => 'Failed to import CSV data: '.$e->getMessage()], 500);
        }
    }
}
