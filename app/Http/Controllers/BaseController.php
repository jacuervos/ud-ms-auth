<?php
  
namespace App\Http\Controllers;
  
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse; // Importa JsonResponse
use App\Http\Controllers\Controller as Controller;
  
class BaseController extends Controller
{
    /**
     * Success response method.
     */
    public function sendResponse($result, $message): JsonResponse
    {
        $response = [
            'success' => true,
            'data'    => $result,
            'message' => $message,
        ];
  
        return response()->json($response, 200); // Laravel ya devuelve JsonResponse aquí
    }
  
    /**
     * Return error response.
     */
    public function sendError($error, $errorMessages = [], $code = 404): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $error,
        ];
  
        if (!empty($errorMessages)) {
            $response['data'] = $errorMessages;
        }
  
        return response()->json($response, $code); // Laravel ya devuelve JsonResponse aquí
    }
}
