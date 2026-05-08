<?php

namespace App\Http\Controllers;
abstract class Controller

{
    protected function prepareTranslations(array $translations, array $columns): array
    {
        $preparedTranslations = [];

        foreach ($translations as $translation) {
            foreach ($translation as $lang => $value) {
                // Initialize the language key if not already present
                if (!isset($preparedTranslations[$lang])) {
                    $preparedTranslations[$lang] = [];
                }

                // Merge translation columns for the same language
                foreach ($columns as $column) {
                    if (isset($value[$column])) {
                        $preparedTranslations[$lang][$column] = $value[$column];
                    } else {
                        info("{$column} not set for language: $lang");
                    }
                }
            }
        }

        return $preparedTranslations;
    }

    protected function success($data = [], string $message = 'Operation successful', int $status = 200)
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ], $status);
    }
    protected function responsePagination($paginator, $data = [], string $message = 'Operation successful', int $status = 200)
    {
        // Check if the first parameter is a paginator
        if ($paginator instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            $pagination = [
                'current_page' => $paginator->currentPage(),
                'total_pages' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'links' => [
                    'first' => $paginator->url(1),
                    'last' => $paginator->url($paginator->lastPage()),
                    'prev' => $paginator->previousPageUrl(),
                    'next' => $paginator->nextPageUrl(),
                ]
            ];
        } else {
            $pagination = null; // No pagination if not a paginated result
        }

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
            'pagination' => $pagination, // Now pagination will not be null if it's a paginated result
        ], $status);
    }
    protected function error(string $message = 'An error occurred', int $status = 400)
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
        ], $status);
    }
    public function uploadPhoto($file, $path='uploads')
    {
       $photoName = md5(time().$file->getFilename()).'.'.$file->getClientOriginalExtension();
       return $file->storeAs($path, $photoName, 'public');
    }
    public function deletePhoto($path)
    {
        $fullPath = storage_path('app/public/' . $path);
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }
}
