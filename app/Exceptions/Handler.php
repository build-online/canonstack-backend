<?php

namespace App\Exceptions;

use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            if ($this->shouldReport($e) && app()->bound('sentry')) {
                app('sentry')->captureException($e);
            }
        });

        $this->renderable(function (ValidationException $e, $request) {
            return response()->sendError($e->getMessage(), 422, $e->errors());
        });

        $this->renderable(function (HttpException $e, $request) {
            return response()->sendError($e->getMessage(), $e->getStatusCode());
        });

        // $this->renderable(function (Exception $e, $request) {      
        //     $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
        //     $message = ($statusCode == 500) ? 'Whoops, looks like something went wrong' : $e->getMessage();
            
        //     $data = [
        //         'id' => Str::random(15),
        //         'status' => $statusCode,
        //     ];

        //     if (config('app.debug')) {
        //         $message = $e->getMessage();
        //         $data['trace'] = $e->getTrace();
        //     }
            
        //     return response()->sendResponse($data, $message, null, false, $statusCode);
        // });
    }
}
