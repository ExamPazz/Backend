<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Section;
use App\Models\Chapter;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\Objective;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ImportKeyController extends Controller
{
    public function importStructureForComm(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'csv_file' => 'required|file|mimes:csv,txt,xlsx',
            'subject' => 'required|string|max:255', // Validate the subject field
        ]);
        
            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 422);
            }
        
            $csvFile = $request->file('csv_file');
        
            $xlsxPath = $csvFile->getRealPath();
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($xlsxPath);
            $sheet = $spreadsheet->getActiveSheet();
        
            DB::beginTransaction();
        
            try {
                $subjectName = $request->input('subject');
                $subject = Subject::firstOrCreate(['name' => $subjectName]);
        
                foreach ($sheet->getRowIterator(2) as $row) {
                    $cells = $row->getCellIterator();
                    $cells->setIterateOnlyExistingCells(false);
        
                    $sectionRaw = $cells->current()->getValue(); $cells->next();
                    $chapterRaw = $cells->current()->getValue(); $cells->next();
                    $topicRaw = $cells->current()->getValue(); $cells->next();
                    $objectiveRaw = $cells->current()->getValue();
        
                    $sectionLines = explode("\n", $sectionRaw); // Split sections into lines

                    foreach ($sectionLines as $sectionLine) {
                        $sectionLine = trim($sectionLine); // Remove extra spaces
                        if (empty($sectionLine)) {
                            continue; // Skip empty lines
                        }
                        
                        [$sectionCode, $sectionBody] = array_pad(preg_split('/[.:]/', $sectionLine, 2), 2, null);
                        
                        $sectionCode = trim($sectionCode);
                        $sectionBody = trim($sectionBody);
                        
                        $section = Section::firstOrCreate([
                            'subject_id' => $subject->id,
                            'code' => $sectionCode,
                        ], [
                            'body' => $sectionBody,
                        ]);
                        
                        if (isset($topicRaw)) {
                            $topicLines = preg_split('/[\n;]+/', $topicRaw); // Split on newlines or semicolons
                        
                            foreach ($topicLines as $topicLine) {
                                $topicLine = trim($topicLine); // Remove extra spaces
                                if (empty($topicLine)) {
                                    continue; // Skip empty lines
                                }
                        
                                // Correcting delimiter from '.' to ':'
                                [$topicCode, $topicBody] = array_pad(preg_split('/\s*[.:]\s*/', $topicLine, 2), 2, null);
                        
                                // if (!$topicBody) {
                                //     $topicBody = preg_replace('/^[a-z]\s*/i', '', $topicLine);  // Remove code if delimiter is missing
                                // }
                                
                                $topicCode = trim($topicCode);
                                $topicBody = trim($topicBody);
                        
                                $topic = Topic::firstOrCreate([
                                    'subject_id' => $subject->id,
                                    'section_id' => $section->id,
                                    'code' => $topicCode,  // Stores only 'a'
                                ], [
                                    'body' => $topicBody, // Stores only 'Techniques in separation'
                                ]);
                            }
                        }
                        
                    }
                        
        
                    [$chapterCode, $chapterBody] = array_pad(explode('.', $chapterRaw, 2), 2, null);
                    $chapter = Chapter::firstOrCreate([
                        'subject_id' => $subject->id,
                        'body' => trim($chapterBody),
                ], [
                        'code' => trim($chapterCode),
                    ]);
        
        
               
                    $lines = preg_split('/[;\n]/', $objectiveRaw); // Split by semicolon or newline

                    foreach ($lines as $line) {
                        $line = trim(str_replace("\u{00A0}", '', $line)); // Remove non-breaking spaces
                        if (empty($line)) continue;
                    
                        if (preg_match('/^\s*(iv|v|vii|viii|ix|xi|xii|xiii|xiv|xv|xvi|xvii|xviii|xix|x|xx|xxi|xxii|xxiii|xxiv|vi|iii|ii|i)\.\s*(.+)$/i', $line, $matches)) {
                            $objectiveCode = trim($matches[1]);
                            $objectiveBody = trim($matches[2]);
                    
                            if (isset($topic)) {
                                Objective::firstOrCreate([
                                    'topic_id' => $topic->id, 
                                    'body' => $objectiveBody,
                                ], [
                                    'code' => $objectiveCode,
                                ]);
                            } else {
                                // Log or handle the missing topic scenario
                                dd("No topic found for objective: {$objectiveBody}");
                            }
                        }
                    }                    

            }  
                DB::commit();
                return response()->json(['message' => 'Data imported successfully!']);
            } catch (\Exception $e) {
                 DB::rollBack();
                return response()->json(['error' => $e->getMessage()], 500);
            }

    }

    public function importStructureforBio(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'csv_file' => 'required|file|mimes:csv,txt',
            'subject' => 'required|string|max:255', // Validate the subject field
        ]);
        
            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 422);
            }
        
            $csvFile = $request->file('csv_file');
        
            $xlsxPath = $csvFile->getRealPath();
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($xlsxPath);
            $sheet = $spreadsheet->getActiveSheet();
        
            DB::beginTransaction();
        
            try {
                $subjectName = $request->input('subject');
                $subject = Subject::firstOrCreate(['name' => $subjectName]);
        
                foreach ($sheet->getRowIterator(2) as $row) {
                    $cells = $row->getCellIterator();
                    $cells->setIterateOnlyExistingCells(false);
        
                    $sectionRaw = $cells->current()->getValue(); $cells->next();
                    $chapterRaw = $cells->current()->getValue(); $cells->next();
                    $topicRaw = $cells->current()->getValue(); $cells->next();
                    $objectiveRaw = $cells->current()->getValue();
        
                    $sectionLines = explode("\n", $sectionRaw); // Split sections into lines

                    foreach ($sectionLines as $sectionLine) {
                        $sectionLine = trim($sectionLine); // Remove extra spaces
                        if (empty($sectionLine)) {
                            continue; // Skip empty lines
                        }                    
                        $sectionParts = explode(':', $sectionLine, 2);
                        
                        $sectionCode = isset($sectionParts[0]) ? trim($sectionParts[0]) : null;
                        $sectionBody = isset($sectionParts[1]) ? trim($sectionParts[1]) : null;   
                        
                        ("Section Code: $sectionCode, Section Body: $sectionBody");

                        
                        $section = Section::firstOrCreate([
                            'subject_id' => $subject->id,
                            'code' => $sectionCode,
                        ], [
                            'body' => $sectionBody,
                        ]);
                        
                        if (isset($topicRaw)) { // Ensure $topicsRaw is defined
                            $topicLines = explode("\n", $topicRaw); // Split topics into lines
                        
                            foreach ($topicLines as $topicLine) {
                                $topicLine = trim($topicLine); // Remove extra spaces
                                if (empty($topicLine)) {
                                    continue; // Skip empty lines
                                }
                        
                                [$topicCode, $topicBody] = array_pad(explode('.', $topicLine, 2), 2, null);
                        
                                $topicCode = trim($topicCode);
                                $topicBody = trim($topicBody);
                        
                                $topic = Topic::firstOrCreate([
                                    'subject_id' => $subject->id,
                                    'section_id' => $section->id, // Link the topic to the current section
                                    'body' => $topicBody,
                                ], [
                                    'code' => $topicCode,
                                ]);
                        
                            }
                        }
                    }
                        
        
                    [$chapterCode, $chapterBody] = array_pad(explode('.', $chapterRaw, 2), 2, null);
                    $chapter = Chapter::firstOrCreate([
                        'subject_id' => $subject->id,
                        'body' => trim($chapterBody),
                ], [
                        'code' => trim($chapterCode),
                    ]);
        
        
                $lines = explode("\n", $objectiveRaw);

                // Loop through each line
                foreach ($lines as $line) {
                    $line = trim($line); // Remove unnecessary spaces
                    if (empty($line)) {
                        continue;
                    }

                    if (preg_match('/^(i+)\.\s+(.*)$/i', $line, $matches)) {
                        $objectiveCode = trim($matches[1]);
                        $objectiveBody = trim($matches[2]);

                        Objective::firstOrCreate([
                            'topic_id' => $topic->id, 
                            'body' => $objectiveBody,
                        ], [
                            'code' => $objectiveCode,
                        ]);
                    }
                }

            }  
                DB::commit();
                return response()->json(['message' => 'Data imported successfully!']);
            } catch (\Exception $e) {
                 DB::rollBack();
                return response()->json(['error' => $e->getMessage()], 500);
            }
    } 

    public function importStructureforGeo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'csv_file' => 'required|file|mimes:csv,txt',
            'subject' => 'required|string|max:255', // Validate the subject field
        ]);
        
            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 422);
            }
        
            $csvFile = $request->file('csv_file');
        
            $xlsxPath = $csvFile->getRealPath();
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($xlsxPath);
            $sheet = $spreadsheet->getActiveSheet();
        
            DB::beginTransaction();
        
            try {
                $subjectName = $request->input('subject');
                $subject = Subject::firstOrCreate(['name' => $subjectName]);
        
                foreach ($sheet->getRowIterator(2) as $row) {
                    $cells = $row->getCellIterator();
                    $cells->setIterateOnlyExistingCells(false);
        
                    $sectionRaw = $cells->current()->getValue(); $cells->next();
                    $chapterRaw = $cells->current()->getValue(); $cells->next();
                    $topicRaw = $cells->current()->getValue(); $cells->next();
                    $objectiveRaw = $cells->current()->getValue();
        
                    $sectionLines = explode("\n", $sectionRaw); // Split sections into lines

                    foreach ($sectionLines as $sectionLine) {
                        $sectionLine = trim($sectionLine); // Remove extra spaces
                        if (empty($sectionLine)) {
                            continue; // Skip empty lines
                        }                    
                        $sectionParts = explode('.', $sectionLine, 2);
                        
                        $sectionCode = isset($sectionParts[0]) ? trim($sectionParts[0]) : null;
                        $sectionBody = isset($sectionParts[1]) ? trim($sectionParts[1]) : null;   
                        
                        ("Section Code: $sectionCode, Section Body: $sectionBody");

                        
                        $section = Section::firstOrCreate([
                            'subject_id' => $subject->id,
                            'code' => $sectionCode,
                        ], [
                            'body' => $sectionBody,
                        ]);
                        
                        if (isset($topicRaw)) { // Ensure $topicsRaw is defined
                            $topicLines = explode("\n", $topicRaw); // Split topics into lines
                        
                            foreach ($topicLines as $topicLine) {
                                $topicLine = trim($topicLine); // Remove extra spaces
                                if (empty($topicLine)) {
                                    continue; // Skip empty lines
                                }
                        
                                [$topicCode, $topicBody] = array_pad(explode('.', $topicLine, 2), 2, null);
                        
                                $topicCode = trim($topicCode);
                                $topicBody = trim($topicBody);
                        
                                $topic = Topic::firstOrCreate([
                                    'subject_id' => $subject->id,
                                    'section_id' => $section->id, // Link the topic to the current section
                                    'body' => $topicBody,
                                ], [
                                    'code' => $topicCode,
                                ]);
                        
                            }
                        }
                    }
                        
        
                    [$chapterCode, $chapterBody] = array_pad(explode('.', $chapterRaw, 2), 2, null);
                    $chapter = Chapter::firstOrCreate([
                        'subject_id' => $subject->id,
                        'body' => trim($chapterBody),
                ], [
                        'code' => trim($chapterCode),
                    ]);
        
        
                $lines = explode("\n", $objectiveRaw);

                // Loop through each line
                foreach ($lines as $line) {
                    $line = trim($line); // Remove unnecessary spaces
                    if (empty($line)) {
                        continue;
                    }

                    if (preg_match('/^(i+)\.\s+(.*)$/i', $line, $matches)) {
                        $objectiveCode = trim($matches[1]);
                        $objectiveBody = trim($matches[2]);

                        Objective::firstOrCreate([
                            'topic_id' => $topic->id, 
                            'body' => $objectiveBody,
                        ], [
                            'code' => $objectiveCode,
                        ]);
                    }
                }

            }  
                DB::commit();
                return response()->json(['message' => 'Data imported successfully!']);
            } catch (\Exception $e) {
                 DB::rollBack();
                return response()->json(['error' => $e->getMessage()], 500);
            }
    } 
    
    public function importStructureforGov(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'csv_file' => 'required|file|mimes:csv,txt',
            'subject' => 'required|string|max:255', // Validate the subject field
        ]);
        
            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 422);
            }
        
            $csvFile = $request->file('csv_file');
        
            $xlsxPath = $csvFile->getRealPath();
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($xlsxPath);
            $sheet = $spreadsheet->getActiveSheet();
        
            DB::beginTransaction();
        
            try {
                $subjectName = $request->input('subject');
                $subject = Subject::firstOrCreate(['name' => $subjectName]);
        
                foreach ($sheet->getRowIterator(2) as $row) {
                    $cells = $row->getCellIterator();
                    $cells->setIterateOnlyExistingCells(false);
        
                    $sectionRaw = $cells->current()->getValue(); $cells->next();
                    $chapterRaw = $cells->current()->getValue(); $cells->next();
                    $topicRaw = $cells->current()->getValue(); $cells->next();
                    $objectiveRaw = $cells->current()->getValue();
        
                    $sectionLines = explode("\n", $sectionRaw); // Split sections into lines
                    foreach ($sectionLines as $sectionLine) {
                        $sectionLine = trim($sectionLine); // Remove extra spaces
                        if (empty($sectionLine)) {
                            continue; // Skip empty lines
                        }

                        // Use a regular expression to match the code and body
                        if (preg_match('/^(I{1,5}\.|[A-Z])\s+(.*)$/', $sectionLine, $matches)) {
                            $sectionCode = trim($matches[1]); // Match group 1 is the code (e.g., "I.")
                            $sectionBody = trim($matches[2]); // Match group 2 is the body (e.g., "ELEMENTS OF GOVERNMENT")

                            // Create or retrieve the section based on the code and subject ID
                            $section = Section::firstOrCreate([
                                'subject_id' => $subject->id,
                                'code' => $sectionCode,
                            ], [
                                'body' => $sectionBody,
                            ]);
                        } else {
                            // Handle the case where the format doesn't match
                            // Log or skip invalid lines
                            continue;
                        }
                        
                        if (isset($topicRaw)) { // Ensure $topicsRaw is defined
                            $topicLines = explode("\n", $topicRaw); // Split topics into lines
                        
                            foreach ($topicLines as $topicLine) {
                                $topicLine = trim($topicLine); // Remove extra spaces
                                if (empty($topicLine)) {
                                    continue; // Skip empty lines
                                }
                        
                                [$topicCode, $topicBody] = array_pad(explode('.', $topicLine, 2), 2, null);
                        
                                $topicCode = trim($topicCode);
                                $topicBody = trim($topicBody);
                        
                                $topic = Topic::firstOrCreate([
                                    'subject_id' => $subject->id,
                                    'section_id' => $section->id, // Link the topic to the current section
                                    'body' => $topicBody,
                                ], [
                                    'code' => $topicCode,
                                ]);
                        
                            }
                        }
                    }
                    if (preg_match('/^\s*(\d+)\:\s*(.+)$/', $chapterRaw, $matches)) {
                        $chapterCode = trim($matches[1]); 
                        $chapterBody = trim($matches[2]);

                        $chapter = Chapter::firstOrCreate([
                            'subject_id' => $subject->id,
                            'code' => $chapterCode,
                        ], [
                            'body' => $chapterBody,
                        ]);
                    } else {
                        dd("Invalid format: " . $chapterRaw);
                    }

        
        
                $lines = explode("\n", $objectiveRaw);

                // Loop through each line
                foreach ($lines as $line) {
                    $line = trim($line); // Remove unnecessary spaces
                    if (empty($line)) {
                        continue;
                    }

                    if (preg_match('/^(i+)\.\s+(.*)$/i', $line, $matches)) {
                        $objectiveCode = trim($matches[1]);
                        $objectiveBody = trim($matches[2]);

                        Objective::firstOrCreate([
                            'topic_id' => $topic->id, 
                            'body' => $objectiveBody,
                        ], [
                            'code' => $objectiveCode,
                        ]);
                    }
                }

            }  
                DB::commit();
                return response()->json(['message' => 'Data imported successfully!']);
            } catch (\Exception $e) {
                 DB::rollBack();
                return response()->json(['error' => $e->getMessage()], 500);
            }

    } 
    
    public function importStructureforEcon(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'csv_file' => 'required|file|mimes:csv,txt',
            'subject' => 'required|string|max:255', // Validate the subject field
        ]);
        
            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 422);
            }
        
            $csvFile = $request->file('csv_file');
        
            $xlsxPath = $csvFile->getRealPath();
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($xlsxPath);
            $sheet = $spreadsheet->getActiveSheet();
        
            DB::beginTransaction();
        
            try {
                $subjectName = $request->input('subject');
                $subject = Subject::firstOrCreate(['name' => $subjectName]);
        
                foreach ($sheet->getRowIterator(2) as $row) {
                    $cells = $row->getCellIterator();
                    $cells->setIterateOnlyExistingCells(false);
        
                    $sectionRaw = $cells->current()->getValue(); $cells->next();
                    $chapterRaw = $cells->current()->getValue(); $cells->next();
                    $topicRaw = $cells->current()->getValue(); $cells->next();
                    $objectiveRaw = $cells->current()->getValue();
        
                    // dd($chapterRaw, $objectiveRaw);
                    $sectionLines = explode("\n", $sectionRaw); // Split sections into lines
                    foreach ($sectionLines as $sectionLine) {
                        $sectionLine = trim($sectionLine); // Remove extra spaces
                        if (empty($sectionLine)) {
                            continue; // Skip empty lines
                        }

                        if (preg_match('/^(I{1,5}|[A-Z])\.?\s+(.*)$/', $sectionLine, $matches)) {
                            $sectionCode = trim($matches[1]); 
                            $sectionBody = trim($matches[2]); 

                            $section = Section::firstOrCreate([
                                'subject_id' => $subject->id,
                                'code' => $sectionCode,
                            ], [
                                'body' => $sectionBody,
                            ]);
                        } else {
                            continue;
                        }
                        
                        if (isset($topicRaw)) {
                            $topicLines = explode("\n", $topicRaw); 
                        
                            foreach ($topicLines as $topicLine) {
                                $topicLine = trim($topicLine); 
                                if (empty($topicLine)) {
                                    continue; 
                                }
                        
                                [$topicCode, $topicBody] = array_pad(explode('.', $topicLine, 2), 2, null);
                        
                                $topicCode = trim($topicCode);
                                $topicBody = trim($topicBody);
                        
                                $topic = Topic::firstOrCreate([
                                    'subject_id' => $subject->id,
                                    'section_id' => $section->id, // Link the topic to the current section
                                    'body' => $topicBody,
                                ], [
                                    'code' => $topicCode,
                                ]);
                        
                            }
                        }
                    }
                    if (preg_match('/^\s*(\d+)\.\s*(.+)$/', $chapterRaw, $matches)) { // Adjusted for period instead of colon
                        $chapterCode = trim($matches[1]); 
                        $chapterBody = trim($matches[2]);
                    
                        $chapter = Chapter::firstOrCreate([
                            'subject_id' => $subject->id,
                            'code' => $chapterCode,
                        ], [
                            'body' => $chapterBody,
                        ]);
                    } else {
                        dd("Invalid chapter format: " . $chapterRaw);
                    }
                    

        
        
                $lines = explode("\n", $objectiveRaw);

                // Loop through each line
                foreach ($lines as $line) {
                    $line = trim($line); // Remove unnecessary spaces
                    if (empty($line)) {
                        continue;
                    }

                    if (preg_match('/^(i+)\.\s+(.*)$/i', $line, $matches)) {
                        $objectiveCode = trim($matches[1]);
                        $objectiveBody = trim($matches[2]);

                        Objective::firstOrCreate([
                            'topic_id' => $topic->id, 
                            'body' => $objectiveBody,
                        ], [
                            'code' => $objectiveCode,
                        ]);
                    }
                }

            }  
                DB::commit();
                return response()->json(['message' => 'Data imported successfully!']);
            } catch (\Exception $e) {
                 DB::rollBack();
                return response()->json(['error' => $e->getMessage()], 500);
            }

    }
    
    public function importStructureforChem(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'csv_file' => 'required|file|mimes:csv,txt',
            'subject' => 'required|string|max:255', // Validate the subject field
        ]);
        
            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 422);
            }
        
            $csvFile = $request->file('csv_file');
        
            $xlsxPath = $csvFile->getRealPath();
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($xlsxPath);
            $sheet = $spreadsheet->getActiveSheet();
        
            DB::beginTransaction();
        
            try {
                $subjectName = $request->input('subject');
                $subject = Subject::firstOrCreate(['name' => $subjectName]);
        
                foreach ($sheet->getRowIterator(2) as $row) {
                    $cells = $row->getCellIterator();
                    $cells->setIterateOnlyExistingCells(false);
        
                    $sectionRaw = $cells->current()->getValue(); $cells->next();
                    $chapterRaw = $cells->current()->getValue(); $cells->next();
                    $topicRaw = $cells->current()->getValue(); $cells->next();
                    $objectiveRaw = $cells->current()->getValue();
        
                    $sectionLines = explode("\n", $sectionRaw); // Split sections into lines

                    foreach ($sectionLines as $sectionLine) {
                        $sectionLine = trim($sectionLine); // Remove extra spaces
                        if (empty($sectionLine)) {
                            continue; // Skip empty lines
                        }
                        
                        [$sectionCode, $sectionBody] = array_pad(explode(':', $sectionLine, 2), 2, null);
                        
                        $sectionCode = trim($sectionCode);
                        $sectionBody = trim($sectionBody);
                        
                        $section = Section::firstOrCreate([
                            'subject_id' => $subject->id,
                            'code' => $sectionCode,
                        ], [
                            'body' => $sectionBody,
                        ]);
                        
                        if (isset($topicRaw)) {
                            $topicLines = explode("\n", $topicRaw); // Split topics into lines
                        
                            foreach ($topicLines as $topicLine) {
                                $topicLine = trim($topicLine); // Remove extra spaces
                                if (empty($topicLine)) {
                                    continue; // Skip empty lines
                                }
                        
                                // Correcting delimiter from '.' to ':'
                                [$topicCode, $topicBody] = array_pad(explode(':', $topicLine, 2), 2, null);
                        
                                $topicCode = trim($topicCode);
                                $topicBody = trim($topicBody);
                        
                                $topic = Topic::firstOrCreate([
                                    'subject_id' => $subject->id,
                                    'section_id' => $section->id,
                                    'code' => $topicCode,  // Stores only 'a'
                                ], [
                                    'body' => $topicBody, // Stores only 'Techniques in separation'
                                ]);
                            }
                        }
                        
                    }
                        
        
                    [$chapterCode, $chapterBody] = array_pad(explode('.', $chapterRaw, 2), 2, null);
                    $chapter = Chapter::firstOrCreate([
                        'subject_id' => $subject->id,
                        'body' => trim($chapterBody),
                ], [
                        'code' => trim($chapterCode),
                    ]);
        
        
                $lines = explode("\n", $objectiveRaw);

                // Loop through each line
                foreach ($lines as $line) {
                    $line = trim($line); // Remove unnecessary spaces
                    if (empty($line)) {
                        continue;
                    }

                    if (preg_match('/^(i+):\s*(.*)$/i', $line, $matches)) {                        
                        $objectiveCode = trim($matches[1]);
                        $objectiveBody = trim($matches[2]);

                        if (isset($topic)) {
                            Objective::firstOrCreate([
                                'topic_id' => $topic->id, 
                                'body' => $objectiveBody,
                            ], [
                                'code' => $objectiveCode,
                            ]);
                        } else {
                            dd("No topic found for objective: " . $objectiveBody);
                        }
                    }
                }

            }  
                DB::commit();
                return response()->json(['message' => 'Data imported successfully!']);
            } catch (\Exception $e) {
                 DB::rollBack();
                return response()->json(['error' => $e->getMessage()], 500);
            }

    }
    
    public function importStructureforEng(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'csv_file' => 'required|file|mimes:csv,xlsx',
            'subject' => 'required|string|max:255', // Validate the subject field
        ]);
        
            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 422);
            }
        
            $csvFile = $request->file('csv_file');
        
            $xlsxPath = $csvFile->getRealPath();
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($xlsxPath);
            $sheet = $spreadsheet->getActiveSheet();
        
            DB::beginTransaction();
        
            try {
                $subjectName = $request->input('subject');
                $subject = Subject::firstOrCreate(['name' => $subjectName]);
        
                foreach ($sheet->getRowIterator(2) as $row) {
                    $cells = $row->getCellIterator();
                    $cells->setIterateOnlyExistingCells(false);
        
                    $sectionRaw = $cells->current()->getValue(); $cells->next();
                    $chapterRaw = $cells->current()->getValue(); $cells->next();
                    $topicRaw = $cells->current()->getValue(); $cells->next();
                    $objectiveRaw = $cells->current()->getValue();
        
                    $sectionLines = explode("\n", $sectionRaw); // Split sections into lines

                    foreach ($sectionLines as $sectionLine) {
                        $sectionLine = trim($sectionLine); // Remove extra spaces
                        if (empty($sectionLine)) {
                            continue; // Skip empty lines
                        }                    
                        $sectionParts = explode('.', $sectionLine, 2);
                        
                        $sectionCode = isset($sectionParts[0]) ? trim($sectionParts[0]) : null;
                        $sectionBody = isset($sectionParts[1]) ? trim($sectionParts[1]) : null;   
                        
                        ("Section Code: $sectionCode, Section Body: $sectionBody");

                        
                        $section = Section::firstOrCreate([
                            'subject_id' => $subject->id,
                            'code' => $sectionCode,
                        ], [
                            'body' => $sectionBody,
                        ]);
                        
                        if (isset($topicRaw)) { // Ensure $topicsRaw is defined
                            $topicLines = explode("\n", $topicRaw); // Split topics into lines
                        
                            foreach ($topicLines as $topicLine) {
                                $topicLine = trim($topicLine); // Remove extra spaces
                                if (empty($topicLine)) {
                                    continue; // Skip empty lines
                                }
                        
                                [$topicCode, $topicBody] = array_pad(explode('.', $topicLine, 2), 2, null);
                        
                                $topicCode = trim($topicCode);
                                $topicBody = trim($topicBody);
                        
                                $topic = Topic::firstOrCreate([
                                    'subject_id' => $subject->id,
                                    'section_id' => $section->id, // Link the topic to the current section
                                    'body' => $topicBody,
                                ], [
                                    'code' => $topicCode,
                                ]);
                        
                            }
                        }
                    }
                        
        
                    [$chapterCode, $chapterBody] = array_pad(explode('.', $chapterRaw, 2), 2, null);
                    $chapter = Chapter::firstOrCreate([
                        'subject_id' => $subject->id,
                        'body' => trim($chapterBody),
                ], [
                        'code' => trim($chapterCode),
                    ]);
        
        
                $lines = explode("\n", $objectiveRaw);

                // Loop through each line
                foreach ($lines as $line) {
                    // $line = trim($line); // Remove unnecessary spaces
                    // if (empty($line)) {
                    //     continue;
                    // }

                    if (preg_match('/^(i+)\.\s+(.*)$/i', $line, $matches)) {
                        $objectiveCode = trim($matches[1]);
                        $objectiveBody = trim($matches[2]);


                        $objective = Objective::firstOrCreate([
                            'topic_id' => $topic->id, 
                            'body' => $objectiveBody,
                        ], [
                            'code' => $objectiveCode,
                        ]);

                        // dd($objective);

                    }
                }

            }  
                DB::commit();
                return response()->json(['message' => 'Data imported successfully!']);
            } catch (\Exception $e) {
                 DB::rollBack();
                return response()->json(['error' => $e->getMessage()], 500);
            }
    } 

    public function importStructureforLiteng(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'csv_file' => 'required|file|mimes:csv,txt',
            'subject' => 'required|string|max:255', // Validate the subject field
        ]);
        
            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 422);
            }
        
            $csvFile = $request->file('csv_file');
        
            $xlsxPath = $csvFile->getRealPath();
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($xlsxPath);
            $sheet = $spreadsheet->getActiveSheet();
        
            DB::beginTransaction();
        
            try {
                $subjectName = $request->input('subject');
                $subject = Subject::firstOrCreate(['name' => $subjectName]);
        
                foreach ($sheet->getRowIterator(2) as $row) {
                    $cells = $row->getCellIterator();
                    $cells->setIterateOnlyExistingCells(false);
        
                    $sectionRaw = $cells->current()->getValue(); $cells->next();
                    $chapterRaw = $cells->current()->getValue(); $cells->next();
                    $topicRaw = $cells->current()->getValue(); $cells->next();
                    $objectiveRaw = $cells->current()->getValue();
        
                    $sectionLines = explode("\n", $sectionRaw); // Split sections into lines

                    foreach ($sectionLines as $sectionLine) {
                        $sectionLine = trim($sectionLine); // Remove extra spaces
                        if (empty($sectionLine)) {
                            continue; // Skip empty lines
                        }                    
                        $sectionParts = explode(':', $sectionLine, 2);
                        
                        $sectionCode = isset($sectionParts[0]) ? trim($sectionParts[0]) : null;
                        $sectionBody = isset($sectionParts[1]) ? trim($sectionParts[1]) : null;   
                        
                        ("Section Code: $sectionCode, Section Body: $sectionBody");

                        
                        $section = Section::firstOrCreate([
                            'subject_id' => $subject->id,
                            'code' => $sectionCode,
                        ], [
                            'body' => $sectionBody,
                        ]);
                        
                        if (isset($topicRaw)) { // Ensure $topicsRaw is defined
                            $topicLines = explode("\n", $topicRaw); // Split topics into lines
                        
                            foreach ($topicLines as $topicLine) {
                                $topicLine = trim($topicLine); // Remove extra spaces
                                if (empty($topicLine)) {
                                    continue; // Skip empty lines
                                }
                        
                                [$topicCode, $topicBody] = array_pad(explode(':', $topicLine, 2), 2, null);
                        
                                $topicCode = trim($topicCode);
                                $topicBody = trim($topicBody);
                        
                                $topic = Topic::firstOrCreate([
                                    'subject_id' => $subject->id,
                                    'section_id' => $section->id, // Link the topic to the current section
                                    'body' => $topicBody,
                                ], [
                                    'code' => $topicCode,
                                ]);
                        
                            }
                        }
                    }
                        
        
                    [$chapterCode, $chapterBody] = array_pad(explode(':', $chapterRaw, 2), 2, null);
                    $chapter = Chapter::firstOrCreate([
                        'subject_id' => $subject->id,
                        'body' => trim($chapterBody),
                ], [
                        'code' => trim($chapterCode),
                    ]);
        
        
                $lines = explode("\n", $objectiveRaw);

                // Loop through each line
                foreach ($lines as $line) {
                    $line = trim($line); // Remove unnecessary spaces
                    if (empty($line)) {
                        continue;
                    }

                    if (preg_match('/^(i+):\s*(.+)$/i', $line, $matches)) {
                        $objectiveCode = trim($matches[1]);
                        $objectiveBody = trim($matches[2]);

                        Objective::firstOrCreate([
                            'topic_id' => $topic->id, 
                            'body' => $objectiveBody,
                        ], [
                            'code' => $objectiveCode,
                        ]);
                    }
                }

            }  
                DB::commit();
                return response()->json(['message' => 'Data imported successfully!']);
            } catch (\Exception $e) {
                 DB::rollBack();
                return response()->json(['error' => $e->getMessage()], 500);
            }
    } 

    public function importStructureforPhy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'csv_file' => 'required|file|mimes:csv,txt',
            'subject' => 'required|string|max:255', // Validate the subject field
        ]);
        
            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 422);
            }
        
            $csvFile = $request->file('csv_file');
        
            $xlsxPath = $csvFile->getRealPath();
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($xlsxPath);
            $sheet = $spreadsheet->getActiveSheet();
        
            DB::beginTransaction();
        
            try {
                $subjectName = $request->input('subject');
                $subject = Subject::firstOrCreate(['name' => $subjectName]);
        
                foreach ($sheet->getRowIterator(2) as $row) {
                    $cells = $row->getCellIterator();
                    $cells->setIterateOnlyExistingCells(false);
        
                    $sectionRaw = $cells->current()->getValue(); $cells->next();
                    $chapterRaw = $cells->current()->getValue(); $cells->next();
                    $topicRaw = $cells->current()->getValue(); $cells->next();
                    $objectiveRaw = $cells->current()->getValue();
        
                    $sectionLines = explode("\n", $sectionRaw); // Split sections into lines

                    foreach ($sectionLines as $sectionLine) {
                        $sectionLine = trim($sectionLine); // Remove extra spaces
                        if (empty($sectionLine)) {
                            continue; // Skip empty lines
                        }                    
                        $sectionParts = explode(':', $sectionLine, 2);
                        
                        $sectionCode = isset($sectionParts[0]) ? trim($sectionParts[0]) : null;
                        $sectionBody = isset($sectionParts[1]) ? trim($sectionParts[1]) : null;   
                        
                        ("Section Code: $sectionCode, Section Body: $sectionBody");

                        
                        $section = Section::firstOrCreate([
                            'subject_id' => $subject->id,
                            'code' => $sectionCode,
                        ], [
                            'body' => $sectionBody,
                        ]);
                        
                        if (isset($topicRaw)) { // Ensure $topicsRaw is defined
                            $topicLines = explode("\n", $topicRaw); // Split topics into lines
                        
                            foreach ($topicLines as $topicLine) {
                                $topicLine = trim($topicLine); // Remove extra spaces
                                if (empty($topicLine)) {
                                    continue; // Skip empty lines
                                }
                        
                                [$topicCode, $topicBody] = array_pad(explode(':', $topicLine, 2), 2, null);
                        
                                $topicCode = trim($topicCode);
                                $topicBody = trim($topicBody);
                        
                                $topic = Topic::firstOrCreate([
                                    'subject_id' => $subject->id,
                                    'section_id' => $section->id, // Link the topic to the current section
                                    'body' => $topicBody,
                                ], [
                                    'code' => $topicCode,
                                ]);
                        
                            }
                        }
                    }
                        
        
                    [$chapterCode, $chapterBody] = array_pad(explode(':', $chapterRaw, 2), 2, null);
                    $chapter = Chapter::firstOrCreate([
                        'subject_id' => $subject->id,
                        'body' => trim($chapterBody),
                ], [
                        'code' => trim($chapterCode),
                    ]);
        
        
                $lines = explode("\n", $objectiveRaw);

                // Loop through each line
                foreach ($lines as $line) {
                    $line = trim($line); // Remove unnecessary spaces
                    if (empty($line)) {
                        continue;
                    }

                    if (preg_match('/^(i+):\s*(.+)$/i', $line, $matches)) {
                        $objectiveCode = trim($matches[1]);
                        $objectiveBody = trim($matches[2]);

                        Objective::firstOrCreate([
                            'topic_id' => $topic->id, 
                            'body' => $objectiveBody,
                        ], [
                            'code' => $objectiveCode,
                        ]);
                    }
                }

            }  
                DB::commit();
                return response()->json(['message' => 'Data imported successfully!']);
            } catch (\Exception $e) {
                 DB::rollBack();
                return response()->json(['error' => $e->getMessage()], 500);
            }
    } 

    public function importStructureforMaths(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'csv_file' => 'required|file|mimes:csv,txt',
            'subject' => 'required|string|max:255', // Validate the subject field
        ]);
        
            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 422);
            }
        
            $csvFile = $request->file('csv_file');
        
            $xlsxPath = $csvFile->getRealPath();
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($xlsxPath);
            $sheet = $spreadsheet->getActiveSheet();
        
            DB::beginTransaction();
        
            try {
                $subjectName = $request->input('subject');
                $subject = Subject::firstOrCreate(['name' => $subjectName]);
        
                foreach ($sheet->getRowIterator(2) as $row) {
                    $cells = $row->getCellIterator();
                    $cells->setIterateOnlyExistingCells(false);
        
                    $sectionRaw = $cells->current()->getValue(); $cells->next();
                    $chapterRaw = $cells->current()->getValue(); $cells->next();
                    $topicRaw = $cells->current()->getValue(); $cells->next();
                    $objectiveRaw = $cells->current()->getValue();
        
                    $sectionLines = explode("\n", $sectionRaw); // Split sections into lines

                    foreach ($sectionLines as $sectionLine) {
                        $sectionLine = trim($sectionLine); // Remove extra spaces
                        if (empty($sectionLine)) {
                            continue; // Skip empty lines
                        }                    
                        $sectionParts = explode(':', $sectionLine, 2);
                        
                        $sectionCode = isset($sectionParts[0]) ? trim($sectionParts[0]) : null;
                        $sectionBody = isset($sectionParts[1]) ? trim($sectionParts[1]) : null;   
                        
                        ("Section Code: $sectionCode, Section Body: $sectionBody");

                        
                        $section = Section::firstOrCreate([
                            'subject_id' => $subject->id,
                            'code' => $sectionCode,
                        ], [
                            'body' => $sectionBody,
                        ]);
                        
                        if (isset($topicRaw)) { // Ensure $topicsRaw is defined
                            $topicLines = explode("\n", $topicRaw); // Split topics into lines
                        
                            foreach ($topicLines as $topicLine) {
                                $topicLine = trim($topicLine); // Remove extra spaces
                                if (empty($topicLine)) {
                                    continue; // Skip empty lines
                                }
                        
                                [$topicCode, $topicBody] = array_pad(explode(':', $topicLine, 2), 2, null);
                        
                                $topicCode = trim($topicCode);
                                $topicBody = trim($topicBody);
                        
                                $topic = Topic::firstOrCreate([
                                    'subject_id' => $subject->id,
                                    'section_id' => $section->id, // Link the topic to the current section
                                    'body' => $topicBody,
                                ], [
                                    'code' => $topicCode,
                                ]);
                        
                            }
                        }
                    }
                        
        
                    [$chapterCode, $chapterBody] = array_pad(explode(':', $chapterRaw, 2), 2, null);
                    $chapter = Chapter::firstOrCreate([
                        'subject_id' => $subject->id,
                        'body' => trim($chapterBody),
                ], [
                        'code' => trim($chapterCode),
                    ]);
        
        
                $lines = explode("\n", $objectiveRaw);

                // Loop through each line
                foreach ($lines as $line) {
                    $line = trim($line); // Remove unnecessary spaces
                    if (empty($line)) {
                        continue;
                    }

                    if (preg_match('/^(i+):\s*(.+)$/i', $line, $matches)) {
                        $objectiveCode = trim($matches[1]);
                        $objectiveBody = trim($matches[2]);

                        Objective::firstOrCreate([
                            'topic_id' => $topic->id, 
                            'body' => $objectiveBody,
                        ], [
                            'code' => $objectiveCode,
                        ]);
                    }
                }

            }  
                DB::commit();
                return response()->json(['message' => 'Data imported successfully!']);
            } catch (\Exception $e) {
                 DB::rollBack();
                return response()->json(['error' => $e->getMessage()], 500);
            }
    } 

    public function importStructureforAcc(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'csv_file' => 'required|file|mimes:csv,txt,xlsx',
            'subject' => 'required|string|max:255', // Validate the subject field
        ]);
        
            if ($validator->fails()) {
                return response()->json(['error' => $validator->errors()], 422);
            }
        
            $csvFile = $request->file('csv_file');
        
            $xlsxPath = $csvFile->getRealPath();
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($xlsxPath);
            $sheet = $spreadsheet->getActiveSheet();
        
            DB::beginTransaction();
        
            try {
                $subjectName = $request->input('subject');
                $subject = Subject::firstOrCreate(['name' => $subjectName]);
        
                foreach ($sheet->getRowIterator(2) as $row) {
                    $cells = $row->getCellIterator();
                    $cells->setIterateOnlyExistingCells(false);
        
                    $sectionRaw = $cells->current()->getValue(); $cells->next();
                    $chapterRaw = $cells->current()->getValue(); $cells->next();
                    $topicRaw = $cells->current()->getValue(); $cells->next();
                    $objectiveRaw = $cells->current()->getValue();
        
                    $sectionLines = explode("\n", $sectionRaw); // Split sections into lines

                    foreach ($sectionLines as $sectionLine) {
                        $sectionLine = trim($sectionLine); // Remove extra spaces
                        if (empty($sectionLine)) {
                            continue; // Skip empty lines
                        }
                        
                        [$sectionCode, $sectionBody] = array_pad(explode('.', $sectionLine, 2), 2, null);
                        
                        $sectionCode = trim($sectionCode);
                        $sectionBody = trim($sectionBody);
                        
                        $section = Section::firstOrCreate([
                            'subject_id' => $subject->id,
                            'code' => $sectionCode,
                        ], [
                            'body' => $sectionBody,
                        ]);
                        
                        if (isset($topicRaw)) {
                            $topicLines = preg_split('/[\n;]+/', $topicRaw); // Split on newlines or semicolons
                        
                            foreach ($topicLines as $topicLine) {
                                $topicLine = trim($topicLine); // Remove extra spaces
                                if (empty($topicLine)) {
                                    continue; // Skip empty lines
                                }
                        
                                // Correcting delimiter from '.' to ':'
                                [$topicCode, $topicBody] = array_pad(preg_split('/[.:]/', $topicLine, 2), 2, null);
                        
                                $topicCode = trim($topicCode);
                                $topicBody = trim($topicBody);
                        
                                $topic = Topic::firstOrCreate([
                                    'subject_id' => $subject->id,
                                    'section_id' => $section->id,
                                    'code' => $topicCode,  // Stores only 'a'
                                ], [
                                    'body' => $topicBody, // Stores only 'Techniques in separation'
                                ]);
                            }
                        }
                        
                    }
                        
        
                    [$chapterCode, $chapterBody] = array_pad(explode('.', $chapterRaw, 2), 2, null);
                    $chapter = Chapter::firstOrCreate([
                        'subject_id' => $subject->id,
                        'body' => trim($chapterBody),
                ], [
                        'code' => trim($chapterCode),
                    ]);
        
        
               
                    $lines = preg_split('/[;\n]/', $objectiveRaw); // Split by semicolon or newline

                    foreach ($lines as $line) {
                        $line = trim(str_replace("\u{00A0}", '', $line)); // Remove non-breaking spaces
                        if (empty($line)) continue;
                    
                        if (preg_match('/^\s*(iv|v|vii|vi|iii|ii|i)\.\s*(.+)$/i', $line, $matches)) {
                            $objectiveCode = trim($matches[1]);
                            $objectiveBody = trim($matches[2]);
                    
                            if (isset($topic)) {
                                Objective::firstOrCreate([
                                    'topic_id' => $topic->id, 
                                    'body' => $objectiveBody,
                                ], [
                                    'code' => $objectiveCode,
                                ]);
                            } else {
                                // Log or handle the missing topic scenario
                                dd("No topic found for objective: {$objectiveBody}");
                            }
                        }
                    }                    

            }  
                DB::commit();
                return response()->json(['message' => 'Data imported successfully!']);
            } catch (\Exception $e) {
                 DB::rollBack();
                return response()->json(['error' => $e->getMessage()], 500);
            }

    }
}