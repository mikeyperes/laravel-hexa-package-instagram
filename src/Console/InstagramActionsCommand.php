<?php
namespace hexa_package_instagram\Console;
use hexa_package_instagram\Actions\ActionRegistry;
use hexa_package_instagram\Actions\Models\StoredResult;
use Illuminate\Console\Command;
final class InstagramActionsCommand extends Command
{
    protected $signature = 'instagram:actions {--json} {--results} {--action=} {--profile=} {--limit=20}';
    protected $description = 'List registered Instagram actions or read their stored result records.';
    public function handle(ActionRegistry $registry): int {
        $rows = $this->option('results') ? StoredResult::query()->when($this->option('action'), fn ($q,$v) => $q->where('action',$v))
            ->when($this->option('profile'),fn ($q,$v) => $q->where('session',$v))->latest('id')->limit(max(1,min(100,(int)$this->option('limit'))))->get(['id','action','session','record'])->toArray() : $registry->list();
        $this->line(json_encode(['success'=>true, $this->option('results') ? 'results' : 'actions'=>$rows], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)); return self::SUCCESS;
    }
}
