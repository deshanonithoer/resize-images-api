<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http;

class ResizeImageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        $rules = [
            "image" => ["required"],
            "w" => ["required", "regex:/^\d+(\.\d+)?%?$/"], // 50, 50%, 50.123, 50.123%
            "h" => "regex:/^\d+(\.\d+)?%?$/",
            "album_id" => "exists:App\Models\Album,id",
        ];

        // Check whether the image is url or file
        $image = $this->all()["image"] ?? false;
        if ($image && $image instanceof UploadFile) {
            $rules["image"][] = "image";
        } else {
            $rules["image"][] = "url";
        }

        return $rules;
    }
}
