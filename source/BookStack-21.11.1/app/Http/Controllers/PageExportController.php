<?php

namespace BookStack\Http\Controllers;

use BookStack\Entities\Repos\PageRepo;
use BookStack\Entities\Tools\ExportFormatter;
use BookStack\Entities\Tools\PageContent;
use BookStack\Exceptions\NotFoundException;
use BookStack\Uploads\WordExportService;
use RuntimeException;
use Throwable;

class PageExportController extends Controller
{
    protected $pageRepo;
    protected $exportFormatter;

    /**
     * PageExportController constructor.
     */
    public function __construct(PageRepo $pageRepo, ExportFormatter $exportFormatter)
    {
        $this->pageRepo = $pageRepo;
        $this->exportFormatter = $exportFormatter;
        $this->middleware('can:content-export');
    }

    /**
     * Exports a page to a PDF.
     * https://github.com/barryvdh/laravel-dompdf.
     *
     * @throws NotFoundException
     * @throws Throwable
     */
    public function pdf(string $bookSlug, string $pageSlug)
    {
        $page = $this->pageRepo->getBySlug($bookSlug, $pageSlug);
        $page->html = (new PageContent($page))->render();
        $pdfContent = $this->exportFormatter->pageToPdf($page);

        return $this->downloadResponse($pdfContent, $pageSlug . '.pdf');
    }

    /**
     * Export a page to a self-contained HTML file.
     *
     * @throws NotFoundException
     * @throws Throwable
     */
    public function html(string $bookSlug, string $pageSlug)
    {
        $page = $this->pageRepo->getBySlug($bookSlug, $pageSlug);
        $page->html = (new PageContent($page))->render();
        $containedHtml = $this->exportFormatter->pageToContainedHtml($page);

        return $this->downloadResponse($containedHtml, $pageSlug . '.html');
    }

    /**
     * Export the current saved page as a Word document.
     *
     * @throws NotFoundException
     */
    public function word(string $bookSlug, string $pageSlug, WordExportService $exporter)
    {
        $page = $this->pageRepo->getBySlug($bookSlug, $pageSlug);
        try {
            $content = $exporter->export($page);
        } catch (RuntimeException $exception) {
            $this->showErrorNotification($exception->getMessage());
            return redirect($page->getUrl());
        }

        return $this->downloadResponse($content, $pageSlug . '.docx')
            ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    }

    /**
     * Export a page to a simple plaintext .txt file.
     */
    public function plainText(string $bookSlug, string $pageSlug)
    {
        $page = $this->pageRepo->getBySlug($bookSlug, $pageSlug);
        $pageText = $this->exportFormatter->pageToPlainText($page);

        return $this->downloadResponse($pageText, $pageSlug . '.txt');
    }

    /**
     * Export a page to a simple markdown .md file.
     *
     * @throws NotFoundException
     */
    public function markdown(string $bookSlug, string $pageSlug)
    {
        $page = $this->pageRepo->getBySlug($bookSlug, $pageSlug);
        $pageText = $this->exportFormatter->pageToMarkdown($page);

        return $this->downloadResponse($pageText, $pageSlug . '.md');
    }
}
