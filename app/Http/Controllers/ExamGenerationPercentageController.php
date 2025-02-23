<?php

namespace App\Http\Controllers;

use App\Models\ExamGeneratingPercentage;
use App\Models\Section;
use App\Models\Subject;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ExamGenerationPercentageController extends Controller
{
    public function importFromCsv(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'file' => 'required|file|mimes:csv,txt',
                'subject_id' => 'required|exists:subjects,id'
            ]);

            if ($validator->fails()) {
                return ApiResponse::failure('Validation failed', $validator->errors());
            }

            $file = $request->file('file');
            $subject_id = $request->subject_id;

            // Read CSV file
            $csvData = array_map('str_getcsv', file($file->getPathname()));
            $headers = array_shift($csvData); // Remove header row

            // Find the year columns (columns after 'Label')
            $yearColumns = array_slice($headers, 2);

            DB::beginTransaction();

            foreach ($csvData as $row) {
                $sectionCode = $row[0]; // Section number (I, II, III, etc.)
                $label = $row[1]; // Section label

                $sectionModel = Section::query()->where('subject_id', $subject_id)->firstWhere('code', $sectionCode);
                // Process each year's percentage
                foreach ($yearColumns as $index => $year) {
                    $percentage = (float) ($row[$index + 2] ?? 0);

                    // Only save if percentage is not 0
                    if ($percentage > 0) {
                        ExamGeneratingPercentage::create([
                            'subject_id' => $subject_id,
                            'section_id' => $sectionModel->id,
                            'section_code' => $sectionCode,
                            'year' => $year,
                            'percentage_value' => $percentage
                        ]);
                    }
                }
            }

            DB::commit();

            return ApiResponse::success('CSV data imported successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('CSV import failed: ' . $e->getMessage());
            return ApiResponse::failure('Failed to import CSV: ' . $e->getMessage());
        }
    }
}
