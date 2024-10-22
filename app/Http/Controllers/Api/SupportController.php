<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\SupportMail;
use Illuminate\Http\Request;
use Mail;
use Validator;

class SupportController extends Controller
{
    public function support(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|min:10',
        ]);
        if ($validator->fails()) {
            return $this->sendError("Validation Error", $validator->errors(), 422);
        }
        $messageContent = $request->input('message');
        $userEmail = $request->user()->email;

        try{
            Mail::to('mdmaksudbhuiyan595@gmail.com')->queue(new SupportMail($messageContent, $userEmail));

            Mail::to($userEmail)->queue(new SupportMail("Your support request has been received. We will get back to you soon.", 'support@gmail.com'));

            return $this->sendResponse(null, 'Support request submitted and email queued successfully!');

        }catch(\Exception $e){
            return $this->sendError('Errors', $e->getMessage(),) ;
        }
    }

}
