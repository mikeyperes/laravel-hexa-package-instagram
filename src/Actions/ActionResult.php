<?php
namespace hexa_package_instagram\Actions;
use JsonSerializable;
final class ActionResult implements JsonSerializable
{
    public function __construct(
        public bool $success,
        public readonly string $action,
        public readonly string $mode,
        public readonly string $session,
        public array $records = [],
        public array $errors = [],
        public ?string $stop = null,
        public array $routes = [],
        public ?int $campaignId = null,
    ) {}
    public function jsonSerialize(): array
    {
        $data = ['success' => $this->success, 'action' => $this->action, 'mode' => $this->mode, 'session' => $this->session,
            'records' => $this->records, 'errors' => $this->errors, 'stop' => $this->stop, 'routes' => $this->routes];
        if ($this->campaignId !== null) $data['campaign_id'] = $this->campaignId;
        return $data;
    }
}
