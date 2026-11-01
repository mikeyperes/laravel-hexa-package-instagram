<?php
namespace hexa_package_instagram\Actions;
use hexa_package_instagram\Actions\Contracts\InstagramAction;
use InvalidArgumentException;
final class ActionRegistry
{
    private array $actions = [];
    public function __construct() { foreach ([ReadPost::class, ReadAccountPosts::class, ReadProfile::class, ReadTaggedAccounts::class, Follow::class, Unfollow::class, LikePost::class, AddToList::class, ReadFollowing::class, ReadFollowers::class, ReadInbox::class, ReadThread::class, SendDm::class, PublishStory::class, PublishPost::class, ReadStories::class, DownloadStory::class, ReadHighlights::class, HighlightAdd::class, HighlightRemove::class, HighlightCreate::class] as $class) $this->register(new $class); }
    public function register(InstagramAction $action): void { $this->actions[$action->key()] = $action; }
    public function get(string $key): InstagramAction { return $this->actions[$key] ?? throw new InvalidArgumentException('Unknown Instagram action: '.$key); }
    public function list(): array
    {
        return array_values(array_map(fn (InstagramAction $a) => ['key' => $a->key(), 'risk' => $a->risk()->value, 'limit' => $a->limitKey(),
            'modes' => array_map(fn (Mode $m) => $m->value, $a->modes()), 'default_mode' => $a->defaultMode()->value,
            'routes' => $a->routes(), 'parts' => $a->parts(), 'process_key' => in_array(Mode::Human,$a->modes(),true) ? 'instagram-action-'.str_replace('_','-',$a->key()) : null,
            'readback_command' => 'instagram:actions --results --action='.$a->key().' --profile=SESSION --json'], $this->actions));
    }
}
