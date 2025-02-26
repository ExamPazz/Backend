<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use Illuminate\Http\Request;
use App\Models\Section;
use App\Models\Chapter;
use App\Models\Topic;
use App\Models\Objective;
use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use GuzzleHttp\Client;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;


class CsvImportController extends Controller
{
    

    // public function importQuestions(Request $request)
    // {
    //     $request->validate([
    //         'subject_name' => ['required', 'unique:subjects,name'],
    //         'question_file' => ['required', 'file', 'mimes:csv,xlsx']
    //     ]);

    //     $file = $request->file('question_file');
    //     $subject_name = $request->input('subject_name');

    //     try {
    //         // Load the spreadsheet
    //         $spreadsheet = IOFactory::load($file->getRealPath());
    //         $worksheet = $spreadsheet->getActiveSheet();
    //         $rows = $worksheet->toArray();

    //         // Remove header row
    //         $header = array_shift($rows);

    //         DB::beginTransaction();

    //         $subject = Subject::query()->create([
    //             'name' => ucwords($subject_name)
    //         ]);

    //         foreach ($rows as $row) {
    //             // Skip empty rows
    //             if (empty(array_filter($row))) {
    //                 continue;
    //             }

    //             // Map spreadsheet columns to variables with null coalescing
    //             [
    //                 $serial, $year, $questionText, $optionA, $optionB,
    //                 $optionC, $optionD, $correctAnswerExplanation, $solution,
    //                 $sectionName, $chapterNumber, $topicName, $objectiveName, $code
    //             ] = array_pad($row, 14, null);

    //             // Validate essential fields
    //             $validator = Validator::make(
    //                 [
    //                     'question' => $questionText,
    //                     'optionA' => $optionA,
    //                     'optionB' => $optionB,
    //                     'section' => $sectionName,
    //                     'chapter' => $chapterNumber,
    //                     'topic' => $topicName,
    //                     'objective' => $objectiveName
    //                 ],
    //                 [
    //                     'question' => 'required|string',
    //                     'optionA' => 'required|string',
    //                     'optionB' => 'required|string',
    //                     'section' => 'required|string',
    //                     'chapter' => 'required|string',
    //                     'topic' => 'required|string',
    //                     'objective' => 'required|string'
    //                 ]
    //             );

    //             if ($validator->fails()) {
    //                 continue;
    //             }

    //             // Sanitize and validate inputs
    //             $year = filter_var($year, FILTER_VALIDATE_INT) ? (int) $year : null;
    //             $sectionName = substr(trim($sectionName), 0, 191);
    //             $chapterNumber = substr(trim($chapterNumber), 0, 191);
    //             $topicName = substr(trim($topicName), 0, 191);
    //             $objectiveName = substr(trim($objectiveName), 0, 191);

    //             // Handle Section with error checking
    //             $section = Section::query()->firstOrCreate(
    //                 ['code' => $sectionName],
    //                 ['subject_id' => $subject->id]
    //             );

    //             // Handle Chapter with error checking
    //             $chapter = Chapter::query()->firstOrCreate(
    //                 ['code' => $chapterNumber],
    //                 [
    //                     'subject_id' => $subject->id,
    //                     'body' => $chapterNumber
    //                 ]
    //             );

    //             // Handle Topic with error checking
    //             $topic = Topic::query()->firstOrCreate(
    //                 ['code' => $topicName],
    //                 [
    //                     'subject_id' => $subject->id,
    //                     'body' => $topicName
    //                 ]
    //             );

    //             // Handle Objective with error checking
    //             $objective = Objective::query()->firstOrCreate(
    //                 ['code' => $objectiveName],
    //                 [
    //                     'topic_id' => $topic->id,
    //                     'body' => $objectiveName
    //                 ]
    //             );

    //             // Create Question with error checking
    //             $question = Question::query()->create([
    //                 'year' => $year,
    //                 'question' => $questionText,
    //                 'image_url' => null,
    //                 'solution' => $solution,
    //                 'section_id' => $section->id,
    //                 'subject_id' => $subject->id,
    //                 'chapter_id' => $chapter->id,
    //                 'topic_id' => $topic->id,
    //                 'objective_id' => $objective->id,
    //             ]);

    //             // Prepare options array
    //             $options = collect([
    //                 ['value' => $optionA, 'is_correct' => false],
    //                 ['value' => $optionB, 'is_correct' => false],
    //                 ['value' => $optionC, 'is_correct' => false],
    //                 ['value' => $optionD, 'is_correct' => false],
    //             ])->map(function ($option) use ($question, $correctAnswerExplanation) {
    //                 return [
    //                     'question_id' => $question->id,
    //                     'value' => $option['value'],
    //                     'is_correct' => !empty($correctAnswerExplanation) &&
    //                         str_contains($correctAnswerExplanation, $option['value']),
    //                     'created_at' => now(),
    //                     'updated_at' => now(),
    //                 ];
    //             })->filter(function ($option) {
    //                 return !empty($option['value']);
    //             })->toArray();

    //             if (!empty($options)) {
    //                 DB::table('question_options')->insert($options);
    //             }
    //         }

    //         DB::commit();

    //         return response()->json([
    //             'message' => 'File imported successfully!',
    //             'subject_id' => $subject->id
    //         ]);

    //     } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
    //         DB::rollBack();
    //         return response()->json(['error' => 'Invalid file format: '.$e->getMessage()], 422);
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         return response()->json(['error' => 'Import failed: '.$e->getMessage()], 500);
    //     }

    // }

    // private function convertGoogleDriveUrl($url)
    // {
    //     if (preg_match('/drive\.google\.com\/file\/d\/([^\/]+)\//', $url, $matches)) {
    //         return 'https://drive.google.com/uc?export=download&id=' . $matches[1];
    //     }

    //     if (preg_match('/drive\.google\.com\/open\?id=([^&]+)/', $url, $matches)) {
    //         return 'https://drive.google.com/uc?export=download&id=' . $matches[1];
    //     }

    //     return $url;
    // }

    // private function storeGoogleDriveImage($url)
    // {
    //     try {
    //         // Convert Google Drive link to direct URL
    //         $directUrl = $this->convertGoogleDriveUrl($url);

    //         if (!$directUrl) {
    //             throw new \Exception('Invalid Google Drive URL');
    //         }

    //         // Download file contents with SSL verification disabled
    //         $response = Http::withoutVerifying()->get($directUrl);

    //         // Generate unique file name
    //         $fileName = 'images/' . uniqid() . '.png';

    //         // Store the image in Laravel's public storage
    //         Storage::disk('public')->put($fileName, $response->body());

    //         // Return the accessible URL
    //         return asset('storage/' . $fileName);
    //     } catch (\Exception $e) {
    //         dd("Skipping image due to error: " . $e->getMessage());
    //         return null;
    //     }
    // }

    public function importCsv(Request $request)
    {
        ini_set('max_execution_time', 500);
    
        $request->validate([
            'question_file' => ['required', 'file', 'mimes:csv,txt,xlsx'],
            'subject_id' => ['required', 'exists:subjects,id'],
        ]);
    
        $filePath = $request->file('question_file');
        $subjectId = $request->input('subject_id');
    
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return response()->json(['error' => 'CSV file not found or not readable'], 400);
        }
    
        DB::beginTransaction();
    
        try {
            $processedRows = 0;
            $imagesToProcess = [];
    
            $rows = LazyCollection::make(function () use ($filePath) {
                $handle = fopen($filePath->getRealPath(), 'r');
                fgetcsv($handle); // Skip header
                while ($row = fgetcsv($handle)) {
                    yield $row;
                }
                fclose($handle);
            });
    
            $rows->chunk(100)->each(function ($chunk) use ($subjectId, &$processedRows, &$imagesToProcess) {
                foreach ($chunk as $row) {
                    [
                        $serial, $year, $questionText, $image, $optionA, $optionB,
                        $optionC, $optionD, $optionE, $correctOption, $solution,
                        $sectionName, $chapterNumber, $topicName, $objectiveName
                    ] = $row;
    
                    if (empty($year)) {
                        continue;
                    }
                    $topicId = Topic::firstOrCreate([
                        'code' => $topicName
                    ])->id;
                
                    // ✅ Create or get objective with topic_id
                    $objectiveId = Objective::firstOrCreate(
                        [
                            'code' => $objectiveName,
                            'topic_id' => $topicId,
                        ],
                        [
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    )->id;
                
                    $question = Question::create([
                        'subject_id' => $subjectId,
                        'year' => $year,
                        'question' => $questionText,
                        'image_url' => $image ?: null,
                        'solution' => $solution,
                        'section_id' => Section::firstOrCreate(['code' => $sectionName, 'subject_id' => $subjectId])->id,
                        'chapter_id' => Chapter::firstOrCreate(['code' => $chapterNumber, 'subject_id' => $subjectId])->id,
                        'topic_id' => $topicId,
                        'objective_id' => $objectiveId,
                    ]);
    
                    // Handle options
                    foreach (['A' => $optionA, 'B' => $optionB, 'C' => $optionC, 'D' => $optionD, 'E' => $optionE] as $label => $option) {
                        if ($option) {
                            $optionModel = $question->options()->create([
                                'value' => $option,
                                'is_correct' => $correctOption === $label,
                            ]);
    
                            if (str_contains($option, 'drive.google.com')) {
                                $imagesToProcess[] = ['type' => 'option', 'id' => $optionModel->id, 'url' => $option];
                            }
                        }
                    }
    
                    $processedRows++;
                }
            });
    
            DB::commit();
        
            return response()->json([
                'message' => 'CSV data imported successfully! Image processing started in the background.',
                'processed_rows' => $processedRows
            ]);
    
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to import CSV data: ' . $e->getMessage()
            ], 500);
        }
    }

    public function convertImages(Request $request)
    {
        $request->validate([
            'subject_id' => ['required', 'exists:subjects,id'],
        ]);
    
        $subjectId = $request->input('subject_id');
    
        try {
            $convertedCount = 0;
            $failedConversions = [];
    
            // 🔄 Process questions (image_url & solution)
            Question::where('subject_id', $subjectId)
                ->where(function ($query) {
                    $query->where('image_url', 'like', '%exampazz-img.s3.%png%')
                          ->orWhere('solution', 'like', '%exampazz-img.s3.%png%');
                })
                ->chunk(100, function ($questions) use (&$convertedCount, &$failedConversions) {
                    foreach ($questions as $question) {
                        $updatedFields = [];
    
                        if ($question->image_url && str_contains($question->image_url, '.png')) {
                            $jpegUrl = $this->convertAndUploadToNewBucket($question->image_url);
                            if ($jpegUrl) {
                                $updatedFields['image_url'] = $jpegUrl;
                                $convertedCount++;
                            } else {
                                $failedConversions[] = ['type' => 'question_image', 'id' => $question->id];
                            }
                        }
    
                        if ($question->solution && str_contains($question->solution, '.png')) {
                            $jpegUrl = $this->convertAndUploadToNewBucket($question->solution);
                            if ($jpegUrl) {
                                $updatedFields['solution'] = $jpegUrl;
                                $convertedCount++;
                            } else {
                                $failedConversions[] = ['type' => 'question_solution', 'id' => $question->id];
                            }
                        }
    
                        if (!empty($updatedFields)) {
                            $question->update($updatedFields);
                        }
                    }
                });
    
            // 🔄 Process options
            QuestionOption::whereHas('question', function ($query) use ($subjectId) {
                    $query->where('subject_id', $subjectId);
                })
                ->where('value', 'like', '%exampazz-img.s3.%png%')
                ->chunk(100, function ($options) use (&$convertedCount, &$failedConversions) {
                    foreach ($options as $option) {
                        $jpegUrl = $this->convertAndUploadToNewBucket($option->value);
                        if ($jpegUrl) {
                            $option->update(['value' => $jpegUrl]);
                            $convertedCount++;
                        } else {
                            $failedConversions[] = ['type' => 'option', 'id' => $option->id];
                        }
                    }
                });
    
            return response()->json([
                'message' => 'PNG to JPEG conversion completed.',
                'converted_images' => $convertedCount,
                'failed_conversions' => $failedConversions,
            ]);
    
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Conversion failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function convertAndUploadToNewBucket($url)
    {
        try {
            $response = Http::withoutVerifying()->get($url);

            if ($response->successful()) {
                // 🖼️ Convert PNG to JPEG
                $image = Image::make($response->body())->encode('jpeg', 85); // Adjust quality if needed
                $fileName = 'images/' . uniqid() . '.jpeg';

                // ✅ Upload to the new S3 bucket
                Storage::disk('s3')->put($fileName, (string) $image, [
                    'visibility' => 'public',
                ]);

                return Storage::disk('s3')->url($fileName);
            }
        } catch (\Exception $e) {
            Log::error("Failed to convert and upload image: {$e->getMessage()}");
        }

        return null;
    }

    private function convertGoogleDriveUrl($url)
    {
        if (preg_match('/drive\.google\.com\/file\/d\/([^\/]+)\//', $url, $matches)) {
            return 'https://drive.google.com/uc?export=download&id=' . $matches[1];
        }

        if (preg_match('/drive\.google\.com\/open\?id=([^&]+)/', $url, $matches)) {
            return 'https://drive.google.com/uc?export=download&id=' . $matches[1];
        }

        return null;
    }

    public function migrateImagesToCloudinaryBySubject(Request $request)
    {
        $request->validate([
            'subject_id' => 'required|integer|exists:subjects,id',
        ]);
    
        $subjectId = $request->input('subject_id');
        $chunkSize = 50; // Reduce chunk size to prevent timeouts
        $processed = 0;
        $skipped = 0;
        $failed = 0;
    
        DB::transaction(function () use ($subjectId, $chunkSize, &$processed, &$skipped, &$failed) {
    
            // Migrate images from 'questions' table
            Question::where('subject_id', $subjectId)
                ->where(function ($query) {
                    $query->whereNotNull('image')->orWhereNotNull('solution');
                })
                ->chunkById($chunkSize, function ($questions) use (&$processed, &$skipped, &$failed) {
                    foreach ($questions as $question) {
                        $updatedFields = [];
    
                        if ($question->image && !$this->isCloudinaryUrl($question->image)) {
                            $newUrl = $this->migrateToCloudinary($question->image);
                            if ($newUrl) {
                                $updatedFields['image'] = $newUrl;
                                $processed++;
                            } else {
                                $failed++;
                            }
                        } else {
                            $skipped++;
                        }
    
                        if ($question->solution && !$this->isCloudinaryUrl($question->solution)) {
                            $newUrl = $this->migrateToCloudinary($question->solution);
                            if ($newUrl) {
                                $updatedFields['solution'] = $newUrl;
                                $processed++;
                            } else {
                                $failed++;
                            }
                        } else {
                            $skipped++;
                        }
    
                        if (!empty($updatedFields)) {
                            $question->update($updatedFields); // Save immediately
                        }
                    }
                });
    
            // Migrate images from 'question_options' table
            QuestionOption::whereHas('question', fn($q) => $q->where('subject_id', $subjectId))
                ->whereNotNull('value')
                ->chunkById($chunkSize, function ($options) use (&$processed, &$skipped, &$failed) {
                    foreach ($options as $option) {
                        if (!$this->isCloudinaryUrl($option->value)) {
                            $newUrl = $this->migrateToCloudinary($option->value);
                            if ($newUrl) {
                                $option->update(['value' => $newUrl]);
                                $processed++;
                            } else {
                                $failed++;
                            }
                        } else {
                            $skipped++;
                        }
                    }
                });
        });
    
        return response()->json([
            'message' => 'Migration completed for this run.',
            'processed' => $processed,
            'skipped (already migrated)' => $skipped,
            'failed' => $failed,
            'note' => 'You can rerun this endpoint to continue migrating remaining images.',
        ]);
    }
    
    /**
     * Check if the URL is already a Cloudinary URL.
     */
    private function isCloudinaryUrl(string $url): bool
    {
        return str_contains($url, 'res.cloudinary.com');
    }
    
/**
 * Check if the URL is a Google Drive link.
 */
private function isGoogleDriveUrl(string $url): bool
{
    return str_contains($url, 'drive.google.com');
}

/**
 * Convert Google Drive URL to direct download link.
 */
private function convertGoogleDriveToDirectLink(string $url): ?string
{
    if (preg_match('/drive\.google\.com\/file\/d\/([^\/]+)/', $url, $matches)) {
        $fileId = $matches[1];
        return "https://drive.google.com/uc?export=download&id={$fileId}";
    }

    return null;
}

private function migrateToCloudinary(string $googleDriveUrl): string
{
    try {
        $directLink = $this->convertGoogleDriveToDirectLink($googleDriveUrl);

        if (!$directLink) {
            throw new \Exception("Failed to convert Google Drive URL: {$googleDriveUrl}");
        }

        Log::info("Converted Google Drive URL: {$directLink}");

        $client = new Client([
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            ],
        ]);

        $response = $client->get($directLink, ['stream' => true]);

        if ($response->getStatusCode() !== 200) {
            throw new \Exception("Failed to download image. HTTP Status: {$response->getStatusCode()}");
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'img_');
        file_put_contents($tempFile, $response->getBody()->getContents());

        if (filesize($tempFile) === 0) {
            throw new \Exception("Downloaded file is empty.");
        }

        // Check if the file is a valid image
        $mimeType = mime_content_type($tempFile);
        Log::info("Downloaded file MIME type: {$mimeType}");

        if (!str_starts_with($mimeType, 'image/')) {
            unlink($tempFile);
            throw new \Exception("Downloaded file is not an image. MIME type: {$mimeType}");
        }

        Log::info("Uploading to Cloudinary: {$tempFile}");

        // Upload using 'file://' to ensure Cloudinary treats it as a local file
        $uploaded = Cloudinary::upload($tempFile, [
            'folder' => 'exampazz',
        ]);        
        
        // dd($uploaded);
        
        if (!$uploaded->getSecurePath()) {
            throw new \Exception("Cloudinary upload failed or returned no URL.");
        }

        unlink($tempFile); // Clean up

        if (!$uploaded->getSecurePath()) {
            throw new \Exception("Cloudinary upload failed or returned no URL.");
        }

        Log::info("Uploaded to Cloudinary URL: " . $uploaded->getSecurePath());

        return $uploaded->getSecurePath();

    } catch (\Exception $e) {
        Log::error("Migration error: " . $e->getMessage());
        return $googleDriveUrl; // Return original URL on failure
    }
}

public function test()
{
    $url = "https://drive.google.com/file/d/1fkX1gXafTC8fMVYY7U_FDpUsqiYTbiwx/view?usp=drive_link";
    $newUrl = $this->migrateToCloudinary($url);
    dd($newUrl); 
}

}
