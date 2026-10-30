<?php

namespace BookStack\Console\Commands;

use BookStack\Entities\Models\Page;
use BookStack\Entities\Tools\SearchText;
use BookStack\Uploads\ExcelPageLayout;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairDocumentSearch extends Command
{
    protected $signature = 'vlex:repair-document-search {--after=0 : Resume after page ID}';
    protected $description = 'Repair legacy Excel types, plain text and page indexes in bounded transactions';

    public function handle()
    {
        $count = 0;
        Page::query()->where('id', '>', (int) $this->option('after'))->select(['id','name','html','text','document_type','pdf_attachment_id'])
            ->chunkById(1, function ($pages) use (&$count) {
                foreach ($pages as $page) {
                    DB::transaction(function () use ($page) {
                        $type = $page->pdf_attachment_id !== null ? 'pdf'
                            : (ExcelPageLayout::matches($page->html ?? '') ? 'excel' : $page->document_type);
                        $text = $type === 'pdf' ? $page->text : SearchText::fromHtml($page->html ?? '');
                        DB::table('pages')->where('id', $page->id)->update(['document_type' => $type, 'text' => $text]);
                        $page->document_type = $type;
                        $page->text = $text;
                        $page->indexForSearch();
                    });
                    $count++;
                    $this->line('Indexed page ' . $page->id);
                }
            });
        $this->info('Repaired ' . $count . ' pages.');
        return 0;
    }
}
