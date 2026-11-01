<?php
namespace hexa_package_instagram\Actions\Contracts;
use hexa_package_instagram\Actions\{ActionRequest, ActionResult, Mode, Risk};
interface InstagramAction
{
    public function key(): string;
    public function risk(): Risk;
    public function limitKey(): string;
    public function modes(): array;
    public function defaultMode(): Mode;
    public function routes(): array;
    public function parts(): array;
    public function run(ActionRequest $request): ActionResult;
}
