<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PreviewNotificationTemplateRequest;
use App\Http\Requests\Admin\StoreNotificationTemplateRequest;
use App\Http\Requests\Admin\UpdateNotificationTemplateRequest;
use App\Http\Resources\NotificationTemplateResource;
use App\Events\Audit\NotificationTemplateUpdated;
use App\Models\Admin;
use App\Models\NotificationTemplate;
use App\Services\Notifications\NotificationRenderer;
use App\Services\Notifications\NotificationTemplateEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminNotificationTemplateController extends Controller
{
    public function __construct(
        private readonly NotificationTemplateEngine $templateEngine,
        private readonly NotificationRenderer $renderer,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $templates = NotificationTemplate::query()
            ->orderBy('channel')
            ->orderBy('key')
            ->get();

        return NotificationTemplateResource::collection($templates)
            ->additional(['success' => true]);
    }

    public function store(StoreNotificationTemplateRequest $request): JsonResponse
    {
        $template = NotificationTemplate::query()->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Notification template created.',
            'data' => new NotificationTemplateResource($template),
        ], 201);
    }

    public function show(NotificationTemplate $template): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new NotificationTemplateResource($template),
        ]);
    }

    public function update(
        NotificationTemplate $template,
        UpdateNotificationTemplateRequest $request,
    ): JsonResponse {
        $oldValues = [
            'body' => $template->body,
            'subject' => $template->subject,
            'is_active' => $template->is_active,
            'name' => $template->name,
        ];

        $template->fill($request->validated())->save();

        $fresh = $template->fresh() ?? $template;
        $admin = auth('sanctum')->user();
        event(NotificationTemplateUpdated::fromTemplate(
            $fresh,
            $oldValues,
            [
                'body' => $fresh->body,
                'subject' => $fresh->subject,
                'is_active' => $fresh->is_active,
                'name' => $fresh->name,
            ],
            $admin instanceof Admin ? $admin : null,
        ));

        return response()->json([
            'success' => true,
            'message' => 'Notification template updated.',
            'data' => new NotificationTemplateResource($fresh),
        ]);
    }

    public function preview(
        NotificationTemplate $template,
        PreviewNotificationTemplateRequest $request,
    ): JsonResponse {
        $variables = $request->validated('variables') ?? [];
        $rendered = $this->templateEngine->preview($template, $variables, $this->renderer);

        return response()->json([
            'success' => true,
            'data' => [
                'template' => new NotificationTemplateResource($template),
                'rendered' => $rendered,
            ],
        ]);
    }
}
