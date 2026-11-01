<?php
namespace hexa_package_instagram\Actions;
final class SendDm extends HumanAction
{
    public function key(): string { return 'send_dm'; }
    public function limitKey(): string { return 'send_dm'; }
}
