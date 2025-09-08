<?php

namespace App\Providers;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\ServiceProvider;
use League\Fractal\TransformerAbstract;

class FractalServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        response()->macro('sendResponse', function (
            $data,
            TransformerAbstract $transformer = null,
            $message = '',
            array $includes = [],
            array $excludes = [],
            $status = 200,
            $success = true
        ) {
            if ($transformer) {
                $includes = join(',', $includes);
                $excludes = join(',', $excludes);
                
                $data = fractal($data, $transformer)->parseIncludes($includes)->parseExcludes($excludes)->toArray();
                $meta = Arr::get($data, 'meta');
                $data = Arr::except($data, ['meta']);

                $response = [
                    'success' => $success,
                    'data' => $data,
                    'message' => $message
                ];
                if ($meta) {
                    $response = Arr::add($response, 'meta', $meta);
                }
                
                return response()->json(
                    $response,
                    $status
                );
            }
            $response = [
                'success' => $success,
                'data' => $data,
                'message' => $message
            ];
            return response()->json(
                $response,
                $status
            );

        });

        response()->macro('sendError', function (
            $message = '',
            $status = 500,
            $errors = []
        ) {
            $response = [
                'id' => Str::random(15),
                'message' => $message,
                'errors' => $errors,
                'status' => $status,
                'success' => false,
            ];
            return response()->json(
                $response,
                $status
            );
        });
    }
}
