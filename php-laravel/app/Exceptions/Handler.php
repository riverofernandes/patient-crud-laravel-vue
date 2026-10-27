<?php

namespace App\Exceptions;

use App\Http\Controllers\AttachmentController;
use App\Models\Consultation;
use App\Models\ConsultationAttachment;
use App\Models\Patient;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // ModelNotFoundException is converted to NotFoundHttpException before reaching here.
        $this->renderable(function (NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $previous = $e->getPrevious();

            $message = match ($previous instanceof ModelNotFoundException ? $previous->getModel() : null) {
                Patient::class => 'Paciente não encontrado.',
                Consultation::class => 'Atendimento não encontrado.',
                ConsultationAttachment::class => AttachmentController::NOT_FOUND_MESSAGE,
                default => 'Recurso não encontrado.',
            };

            return response()->json(['message' => $message], 404);
        });

        // Body above post_max_size (12 MB): rejected before validation, so the upload rules (and their pt-BR
        // messages with the per-route limit) never run.
        $this->renderable(function (PostTooLargeException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'O arquivo enviado é grande demais.'], 413);
            }
        });

        $this->renderable(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(
                    ['message' => 'Muitas requisições. Tente novamente em instantes.'],
                    429,
                    $e->getHeaders()
                );
            }
        });
    }

    /**
     * Routing errors (e.g. 405) happen before the api middleware forces JSON.
     */
    protected function shouldReturnJson($request, Throwable $e): bool
    {
        return $request->is('api/*') || parent::shouldReturnJson($request, $e);
    }
}
