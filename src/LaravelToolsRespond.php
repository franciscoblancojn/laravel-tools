<?php

namespace franciscoblancojn\LaravelTools;


use Closure;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Http\JsonResponse;

class LaravelToolsRespond
{
    private $function_log = null;
    public function __construct($function_log = null)
    {
        $this->function_log = $function_log;
    }
    public function handle(Request $request, Closure $next)
    {
        $data = null;
        try {
            if ($request->header('Accept') !== 'application/json') {
                $request->headers->set('Accept', 'application/json');
            }
            if ($request->header('Content-Type') !== 'application/json') {
                $request->headers->set('Content-Type', 'application/json');
            }
            $response = $next($request);
            $status = $response->getStatusCode();

            if ($status < 200 || $status >= 300) {

                if (property_exists($response, 'exception') && $response->exception) {
                    $e = $response->exception;

                    return response()->json([
                        'success' => false,
                        'code' => $e->getCode() ?: 500,
                        'message' => $e->getMessage(),
                        'error' => [
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                        ],
                    ], $e->getCode() ?: 500);
                }

                $message = 'Error';

                if ($response instanceof JsonResponse) {
                    $data = $response->getData(true);
                    if (!empty($data['message'])) {
                        $message = $data['message'];
                    }
                }

                throw new Exception($message, $status);
            }
            // Si es un JsonResponse, extraemos el contenido original
            if ($response instanceof JsonResponse) {
                $data = $response->getData(true);
            } else {
                $data = $response;
            }

            return response()->json([
                'success' => true,
                'code' => $response->getStatusCode(),
                'message' => $request['message'],
                'data' => $data,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
                'error' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ],
            ], $e->getCode() ?: 500);
        } finally {
            if ($request['log_disabled'] !== true && $this->function_log) {
                call_user_func(
                    $this->function_log,
                    $request['log_type'] ?? 'UNDEFINED',
                    $request['log_id'] ?? 'UNDEFINED',
                    [
                        'body' => $request->all(),
                        'data' => $data,
                    ]
                );
            }
        }
    }
}
