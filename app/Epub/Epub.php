<?php

namespace App\Epub;

use App\Domain\Chapter;
use Illuminate\Support\Str;

class Epub
{
    private string $tempDir;

    public function __construct()
    {
        $this->tempDir = storage_path("app/" . uniqid('epub_', true));
    }

    /**
     * @param string $bookName
     * @param Chapter[] $chapters
     * @return string
     * @throws \Exception
     */
    public function generate(string $bookName, array $chapters): string
    {
        $this->prepareDirectories();

        $this->createMimetype();
        $this->createContainerXML();
        $this->createContentOPF($bookName, $chapters);
        $this->createChapters($chapters);

        $epubFile = $this->generateEPUBFile($bookName, $chapters);

        $this->cleanTemporaryFiles();

        return $epubFile;
    }

    private function prepareDirectories(): void
    {
        mkdir($this->tempDir);
        mkdir($this->tempDir . '/META-INF');
    }

    private function createMimetype(): void
    {
        file_put_contents($this->tempDir . '/mimetype', 'application/epub+zip');
    }


    private function createContainerXML(): void
    {
        $containerXML = '<?xml version="1.0"?>
                <container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
                    <rootfiles>
                        <rootfile full-path="content.opf" media-type="application/oebps-package+xml"/>
                    </rootfiles>
                </container>';

        file_put_contents($this->tempDir . '/META-INF/container.xml', $containerXML);
    }


    /**
     * @param string $bookName
     * @param Chapter[] $chapters
     * @return void
     */
    private function createContentOPF(string $bookName, array $chapters): void
    {
        $manifestItems = '';
        $spineItems = '';

        foreach ($chapters as $index => $chapter) {
            $chapterId = 'chapter' . ($index + 1);
            $manifestItems .= '<item id="' . $chapterId . '" href="' . $chapterId . '.xhtml" media-type="application/xhtml+xml"/>' . "\n";
            $spineItems .= '<itemref idref="' . $chapterId . '"/>' . "\n";
        }

        $contentOPF = '<?xml version="1.0" encoding="UTF-8"?>
                    <package xmlns="http://www.idpf.org/2007/opf" unique-identifier="BookID" version="3.0">
                        <metadata xmlns:dc="http://purl.org/dc/elements/1.1/">
                            <dc:title>' . htmlspecialchars($bookName) . '</dc:title>
                            <dc:language>pt</dc:language>
                            <dc:identifier id="BookID">urn:uuid:' . Str::uuid()->toString() . '</dc:identifier>
                        </metadata>
                        <manifest>
                            ' . $manifestItems . '
                        </manifest>
                        <spine>
                            ' . $spineItems . '
                        </spine>
                    </package>';

        file_put_contents($this->tempDir . '/content.opf', $contentOPF);
    }

    /**
     * @param Chapter[] $chapters
     * @return void
     */
    private function createChapters(array $chapters): void
    {
        foreach ($chapters as $index => $chapter) {
            $chapterContent = '<?xml version="1.0" encoding="UTF-8"?>
            <!DOCTYPE html>
            <html xmlns="http://www.w3.org/1999/xhtml">
                <head>
                    <title>' . htmlspecialchars($chapter->title) . '</title>
                </head>
                <body>
                    <h1>' . htmlspecialchars($chapter->title) . '</h1>
                    <hr />
                    <section>' . nl2br(htmlspecialchars($chapter->content)) . '</section>
                </body>
            </html>';
            file_put_contents($this->tempDir . '/chapter' . ($index + 1) . '.xhtml', $chapterContent);
        }
    }


    private function generateEPUBFile(string $bookName, array $chapters): string
    {
        $epubFile = storage_path('app/' . preg_replace('/[^a-zA-Z0-9]/', '_', strtolower($bookName)) . '.epub');
        $zip = new \ZipArchive();

        if ($zip->open($epubFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            $zip->addFile($this->tempDir . '/mimetype', 'mimetype');

            $zip->addEmptyDir('META-INF');
            $zip->addFile($this->tempDir . '/META-INF/container.xml', 'META-INF/container.xml');

            $zip->addFile($this->tempDir . '/content.opf', 'content.opf');

            foreach ($chapters as $index => $chapter) {
                $chapterFile = 'chapter' . ($index + 1) . '.xhtml';
                $zip->addFile($this->tempDir . '/' . $chapterFile, $chapterFile);
            }

            $zip->close();
        } else {
            throw new \RuntimeException("Erro ao criar o arquivo EPUB.");
        }

        return $epubFile;
    }

    private function cleanTemporaryFiles(): void
    {
        unlink($this->tempDir . '/META-INF/container.xml');
        rmdir($this->tempDir . '/META-INF');
        $files = glob($this->tempDir . '/*');
        foreach ($files as $file) {
            unlink($file);
        }
        rmdir($this->tempDir);
    }
}
