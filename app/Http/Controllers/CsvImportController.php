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

        if (!file_exists($filePath) || !is_readable($filePath)) {
            return response()->json(['error' => 'CSV file not found or not readable'], 400);
        }

        $fileHandle = fopen($filePath->getRealPath(), 'r');
        $header = fgetcsv($fileHandle); // Read header row

        DB::beginTransaction();

        $subject = Subject::query()->create([
            'name' => ucwords($subject_name)
        ]);

        try {
            while (($row = fgetcsv($fileHandle)) !== false) {
                // Map CSV columns to variables
                [
                    $serial, $year, $questionText, $optionA, $optionB,
                    $optionC, $optionD, $correctAnswerExplanation, $solution,
                    $sectionName, $chapterNumber, $topicName, $objectiveName, $code
                ] = $row;
            
                // Skip rows with missing essential fields
                if (
                    empty($questionText) || 
                    empty($optionA) || 
                    empty($optionB) || 
                    empty($sectionName) || 
                    empty($chapterNumber) || 
                    empty($topicName) || 
                    empty($objectiveName)
                ) {
                    continue;
                }
            
                // Sanitize and validate inputs
                $year = is_numeric($year) ? $year : null;
                $sectionName = substr(trim($sectionName), 0, 191);
                $chapterNumber = substr(trim($chapterNumber), 0, 191);
                $topicName = substr(trim($topicName), 0, 191);
                $objectiveName = substr(trim($objectiveName), 0, 191);
            
                // Handle Section
                $section = Section::query()->firstOrCreate(['code' => $sectionName], [
                    'subject_id' => $subject->id,
                ]);
            
                // Handle Chapter
                $chapter = Chapter::firstOrCreate(['code' => $chapterNumber], [
                    'subject_id' => $subject->id,
                    'body' => 'Chapter Description Here', // Update as needed
                ]);
            
                // Handle Topic
                $topic = Topic::query()->firstOrCreate(['code' => $topicName], [
                    'subject_id' => $subject->id,
                    'body' => $topicName,
                ]);
            
                // Handle Objective
                $objective = Objective::query()->firstOrCreate(['code' => $objectiveName], [
                    'topic_id' => $topic->id,
                    'body' => 'Objective Description Here',
                ]);
            
                // Insert Question
                $question = new Question();
                $question->year = $year;
                $question->question = $questionText;
                $question->image_url = null; // No image URL in CSV
                $question->solution = $solution;
                $question->section_id = $section->id;
                $question->subject_id = $subject->id;
                $question->chapter_id = $chapter->id;
                $question->topic_id = $topic->id;
                $question->objective_id = $objective->id;
                $question->save();
            
                // Insert Options
                $options = [
                    ['value' => $optionA, 'is_correct' => false],
                    ['value' => $optionB, 'is_correct' => false],
                    ['value' => $optionC, 'is_correct' => false],
                    ['value' => $optionD, 'is_correct' => false],
                ];
            
                foreach ($options as $index => &$option) {
                    if (strpos($correctAnswerExplanation, $option['value']) !== false) {
                        $option['is_correct'] = true;
                    }
                    $option['question_id'] = $question->id;
                }
            
                DB::table('question_options')->insert($options);
            }            

            DB::commit();
            fclose($fileHandle);
            return response()->json(['message' => 'CSV data imported successfully!']);
        } catch (\Exception $e) {
            DB::rollBack();
            fclose($fileHandle);
            return response()->json(['error' => 'Failed to import CSV data: ' . $e->getMessage()], 500);
        }
    }
}
