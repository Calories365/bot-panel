<?php

namespace App\Http\Controllers;

use App\Models\OnecJob;
use App\Services\TelegramServices\OnecHelperHandlers\OnecJobBoard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The API of the worker on the 1C server, /api/onec-helper: the worker only
 * calls out, takes the questions gathered by the 1C helper bot and gives back
 * its answers. The worker is written apart against this contract: a change
 * of a route or a field is made on both sides.
 */
class OnecHelperController extends Controller
{
    public function __construct(protected OnecJobBoard $board) {}

    /**
     * The oldest job the worker can take, now in work; 204 when there is none.
     */
    public function claim(): JsonResponse|Response
    {
        $job = $this->board->claim();

        if ($job === null) {
            return response()->noContent();
        }

        return $this->json($this->board->payload($job));
    }

    public function file(int $file): JsonResponse|StreamedResponse
    {
        $message = $this->board->file($file);
        $disk = Storage::disk(config('onec_helper.disk'));

        if ($message === null) {
            return $this->json(['error' => 'Not found'], 404);
        }

        if (! $disk->exists($message->file_path)) {
            // Horizon stored it: on the server its container and this one share the
            // files only through a volume on storage/app/onec-helper.
            Log::error('A file of the 1C helper bot is not on the disk of this container', ['file' => $message->id]);

            return $this->json(['error' => 'Not found'], 404);
        }

        return $disk->response($message->file_path, $message->file_name, [
            'Content-Type' => $message->file_mime ?: 'application/octet-stream',
        ]);
    }

    public function heartbeat(Request $request, int $job): JsonResponse|Response
    {
        $body = $this->body($request);
        $validator = Validator::make($body, ['note' => 'nullable|string']);

        if ($validator->fails()) {
            return $this->json(['errors' => $validator->errors()], 422);
        }

        $onecJob = OnecJob::find($job);

        if ($onecJob === null) {
            return $this->json(['error' => 'Not found'], 404);
        }

        if (! $this->board->heartbeat($onecJob, $body['note'] ?? null)) {
            return $this->json(['error' => 'The job is not in work'], 409);
        }

        return response()->noContent();
    }

    /**
     * The answer of an analysis, or for a job "window" or "resume" where the
     * session it opened on the server is: the fields depend on the kind.
     */
    public function result(Request $request, int $job): JsonResponse|Response
    {
        $onecJob = OnecJob::find($job);

        if ($onecJob === null) {
            return $this->json(['error' => 'Not found'], 404);
        }

        $body = $this->body($request);
        $validator = Validator::make($body, $onecJob->opensOnServer() ? [
            'session_id' => 'required|string|max:255',
            'attach_id' => 'required|string|max:255',
            'folder' => 'nullable|string|max:1024',
            'project_dir' => 'required|string|max:1024',
            'window' => 'required|boolean:strict',
        ] : [
            'session_id' => 'required|string|max:255',
            'for_user' => 'required|string',
            // Empty or absent when the agent answered without the separator of the two texts.
            'for_boss' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->json(['errors' => $validator->errors()], 422);
        }

        $saved = $onecJob->opensOnServer()
            ? $this->board->opened($onecJob, $body['session_id'], $body['attach_id'], $body['folder'] ?? null, $body['project_dir'], $body['window'])
            : $this->board->result($onecJob, $body['session_id'], $body['for_user'], $body['for_boss'] ?? null);

        if (! $saved) {
            return $this->json(['error' => 'The job already has its result'], 409);
        }

        return response()->noContent();
    }

    public function fail(Request $request, int $job): JsonResponse|Response
    {
        $body = $this->body($request);
        $validator = Validator::make($body, ['error' => 'required|string']);

        if ($validator->fails()) {
            return $this->json(['errors' => $validator->errors()], 422);
        }

        $onecJob = OnecJob::find($job);

        if ($onecJob === null) {
            return $this->json(['error' => 'Not found'], 404);
        }

        if (! $this->board->fail($onecJob, $body['error'])) {
            return $this->json(['error' => 'The job is already finished'], 409);
        }

        return response()->noContent();
    }

    /**
     * The JSON the worker sent, as it is: the TrimStrings and
     * ConvertEmptyStringsToNull middleware change $request->input(), and the
     * text for the boss goes to the owner exactly as it came.
     *
     * @return array<string, mixed>
     */
    private function body(Request $request): array
    {
        $body = json_decode($request->getContent(), true);

        return is_array($body) ? $body : [];
    }

    /**
     * UTF-8 as it is: the texts are Ukrainian and Russian.
     *
     * @param  array<string, mixed>  $data
     */
    private function json(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status, ['Content-Type' => 'application/json; charset=utf-8'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
