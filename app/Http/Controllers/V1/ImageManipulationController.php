<?php

namespace App\Http\Controllers\V1;

use File;
use Illuminate\Http;
use App\Models\Album;
use Illuminate\Support\Str;
use App\Models\ImageManipulation;
use App\Http\Controllers\Controller;
use Intervention\Image\Facades\Image;
use App\Http\Requests\ResizeImageRequest;
use App\Http\Resources\V1\ImageManipulationResource;
use App\Http\Requests\UpdateImageManipulationRequest;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ImageManipulationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(): AnonymousResourceCollection
    {
        return ImageManipulationResource::collection(ImageManipulation::paginate());
    }

    /**
     * Fetch all images by Album id
     *
     * @param  \App\Models\Album  $album
     * @return ImageManipulationResource
     */
    public function byAlbum(Album $album): AnonymousResourceCollection
    {
        return ImageManipulationResource::collection(ImageManipulation::where(["album_id" => $album->id])->paginate());
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\ResizeImageRequest  $request
     * @return ImageManipulationResource
     */
    public function resize(ResizeImageRequest $request): ImageManipulationResource
    {
        $all = $request->all();

        /** @var UploadedFile|string $image */
        $image = $all["image"];
        unset($all["image"]);

        $data = [
            "type" => ImageManipulation::TYPE_RESIZE,
            "data" => json_encode($all),
            "user_id" => null
        ];

        if (isset($all["album_id"])){
            /** TODO: Check if album exists and belongs to user */
            $data["album_id"] = $all["album_id"];
        }

        /** Setup the upload dir with a random parent folder */
        $dir = "images/" . Str::random() . "/";
        $absolutePath = public_path($dir);
        File::makeDirectory($absolutePath);

        if ($image instanceof UploadedFile) {
            $data["name"] = $image->getClientOriginalName();

            $filename = pathinfo($data["name"], PATHINFO_FILENAME);
            $extension = $image->getClientOriginalExtension();
            $originalPath = $absolutePath . $data["name"];

            /** Upload the file to the upload folder */
            $image->move($absolutePath, $data["name"]);
        } else {
            $basename = pathinfo($image, PATHINFO_BASENAME);

            /** Remove everything from ? to the end of the string */
            $data["name"] = $filename = preg_replace("/\?.*/", "", $basename);

            /** Get the extension with regex */
            $extension = preg_replace("/^.*\.(\w+)$/", "$1", $filename);

            $originalPath = $absolutePath . $filename;

            /** Copy the file to the upload folder */
            copy($image, $absolutePath . $filename);
        }

        $data["path"] = $dir . $filename;

        $w = $all["w"];
        $h = $all["h"] ?? false;

        /** Resize the image */
        list($width, $height, $image) = $this->getImageWidthAndHeight($w, $h, $originalPath);
        $resizedFilename = $filename . "-resized." . $extension;
        $image->resize($width, $height)->save($absolutePath . $resizedFilename);
        $data["output_path"] = $dir . $resizedFilename;

        $imageManipulation = ImageManipulation::create($data);
        return new ImageManipulationResource($imageManipulation);
    }

    private function getImageWidthAndHeight(string $w, string $h, string $originalPath): array {
        $image = Image::make($originalPath);
        $originalWidth = $image->width();
        $originalHeight = $image->height();

        if (str_ends_with($w, "%")) {
            $ratioW = (float) str_replace("%", "", $w);
            $ratioH = $h ? (float) str_replace("%", "", $h) : $ratioW;

            $newWidth = $originalWidth * $ratioW / 100.00;
            $newHeight = $originalHeight * $ratioH / 100.00;
        } else {
            $newWidth = (float) $w;
            $newHeight = $h ? (float) $h : $originalHeight * $newWidth / $originalWidth;
        }

        return [$newWidth, $newHeight, $image];
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\ImageManipulation  $imageManipulation
     * @return ImageManipulationResource
     */
    public function show(ImageManipulation $image): ImageManipulationResource
    {
        return new ImageManipulationResource($image);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\ImageManipulation  $imageManipulation
     * @return ImageManipulationResource
     */
    public function destroy(ImageManipulation $image): ImageManipulationResource
    {
        $image->delete();
        return response(null, 204);
    }
}
