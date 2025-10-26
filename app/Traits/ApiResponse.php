<?php

namespace App\Traits;

trait ApiResponse
{
    /**
     * Return success response
     */
    protected function success($data = null, ?string $message = null, int $code = 200)
    {
        $response = ['success' => true];
        
        if ($message !== null) {
            $response['message'] = $message;
        }
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        return response()->json($response, $code);
    }
    
    /**
     * Return error response
     */
    protected function error(string $message, int $code = 400, $errors = null)
    {
        $response = ['success' => false, 'message' => $message];
        
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        
        return response()->json($response, $code);
    }
    
    /**
     * Return validation error response
     */
    protected function validationError($errors, ?string $message = 'Validation failed')
    {
        return $this->error($message, 422, $errors);
    }
    
    /**
     * Return not found response
     */
    protected function notFound(?string $message = 'Resource not found')
    {
        return $this->error($message, 404);
    }
    
    /**
     * Return unauthorized response
     */
    protected function unauthorized(?string $message = 'Unauthorized')
    {
        return $this->error($message, 401);
    }
    
    /**
     * Return forbidden response
     */
    protected function forbidden(?string $message = 'Forbidden')
    {
        return $this->error($message, 403);
    }
    
    /**
     * Return paginated response
     */
    protected function paginated($data, ?string $message = null)
    {
        $response = [
            'success' => true,
            'data' => $data->items(),
            'pagination' => [
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
            ],
        ];
        
        if ($message !== null) {
            $response['message'] = $message;
        }
        
        return response()->json($response, 200);
    }
}
