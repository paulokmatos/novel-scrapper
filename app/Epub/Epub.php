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
    public function generate(string $bookName, array $chapters, string $chapterRange, string $coverImageUrl = null): string
    {
        $this->prepareDirectories();

        $this->createMimetype();
        $this->createContainerXML();

        $coverImagePath = null;
        if ($coverImageUrl) {
            $coverImagePath = $this->downloadImage($coverImageUrl);
        }

        $this->createContentOPF($bookName, $chapters, $coverImagePath);
        $this->createChapters($chapters);

        if ($coverImagePath) {
            $this->addCoverImage($coverImagePath, $bookName, $chapterRange);
        }

        $this->createTOC($bookName, $chapters);

        $epubFile = $this->generateEPUBFile($bookName, $chapters);

        $this->cleanTemporaryFiles();

        return $epubFile;
    }

    private function downloadImage(string $imageUrl): string
    {
        $tempImagePath = $this->tempDir . '/cover.jpg';
        $imageData = file_get_contents($imageUrl);

        if ($imageData === false) {
            throw new \RuntimeException("Não foi possível baixar a imagem da URL: " . $imageUrl);
        }

        file_put_contents($tempImagePath, $imageData);

        return $tempImagePath;
    }

    private function addCoverImage(string $coverImagePath, string $bookName, string $chapterRange): void
    {
        // Copia a imagem da capa para o diretório temporário
        copy($coverImagePath, $this->tempDir . '/cover.jpg');

        // Cria o arquivo XHTML para a capa
        $coverContent = '<?xml version="1.0" encoding="UTF-8"?>
        <!DOCTYPE html>
        <html xmlns="http://www.w3.org/1999/xhtml">
            <head>
                <title>Capa</title>
                <style>
                    body { text-align: center; font-family: Arial, sans-serif; margin: 0; padding: 0; }
                    .content { margin: 20px; }
                    .title { font-size: 24px; font-weight: bold; margin-top: 20px; }
                    .chapter-range { font-size: 18px; margin-top: 10px; color: #555; }
                </style>
            </head>
            <body>
                <div class="content">
                    <img src="cover.jpg" alt="Capa" style="max-width: 100%; height: auto;" />
                    <div class="title">' . htmlspecialchars($bookName) . '</div>
                    <div class="chapter-range">Capítulos: ' . htmlspecialchars($chapterRange) . '</div>
                </div>
            </body>
        </html>';

        file_put_contents($this->tempDir . '/cover.xhtml', $coverContent);
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

    private function createTOC(string $bookName, array $chapters): void
    {
        $tocContent = '<?xml version="1.0" encoding="UTF-8"?>
        <!DOCTYPE html>
        <html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops">
            <head>
                <title>Índice</title>
            </head>
            <body>
                <h1>' . htmlspecialchars($bookName) . ' - Índice</h1>
                <nav epub:type="toc">
                    <ol>';

        foreach ($chapters as $index => $chapter) {
            $chapterFile = 'chapter' . ($index + 1) . '.xhtml';
            $tocContent .= '<li><a href="' . htmlspecialchars($chapterFile) . '">' . htmlspecialchars(
                    $chapter->title
                ) . '</a></li>';
        }

        $tocContent .= '</ol>
                </nav>
            </body>
        </html>';

        file_put_contents($this->tempDir . '/toc.xhtml', $tocContent);
    }

    private function createContentOPF(string $bookName, array $chapters, ?string $coverImagePath = null): void
    {
        $manifestItems = '';
        $spineItems = '';

        if ($coverImagePath) {
            $manifestItems .= '<item id="cover" href="cover.xhtml" media-type="application/xhtml+xml"/>' . "\n";
            $manifestItems .= '<item id="cover-image" href="cover.jpg" media-type="image/jpeg"/>' . "\n";
            $spineItems .= '<itemref idref="cover" linear="yes"/>' . "\n";
        }

        $manifestItems .= '<item id="toc" href="toc.xhtml" media-type="application/xhtml+xml" properties="nav"/>' . "\n";
        $spineItems .= '<itemref idref="toc" linear="yes"/>' . "\n";

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
                            <dc:identifier id="BookID">urn:uuid:' . Str::uuid()->toString() . '</dc:identifier>';

        if ($coverImagePath) {
            $contentOPF .= '<meta name="cover" content="cover-image"/>';
        }

        $contentOPF .= '</metadata>
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
        $epubFile = storage_path('app/' . $this->sanitizeFileName($bookName) . '.epub');
        $zip = new \ZipArchive();

        if ($zip->open($epubFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
            $zip->addFile($this->tempDir . '/mimetype', 'mimetype');
            $zip->addEmptyDir('META-INF');
            $zip->addFile($this->tempDir . '/META-INF/container.xml', 'META-INF/container.xml');
            $zip->addFile($this->tempDir . '/content.opf', 'content.opf');
            $zip->addFile($this->tempDir . '/toc.xhtml', 'toc.xhtml');

            if (file_exists($this->tempDir . '/cover.xhtml')) {
                $zip->addFile($this->tempDir . '/cover.xhtml', 'cover.xhtml');
            }

            if (file_exists($this->tempDir . '/cover.jpg')) {
                $zip->addFile($this->tempDir . '/cover.jpg', 'cover.jpg');
            }

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

    private function sanitizeFileName(string $fileName): string
    {
        // Substituir caracteres acentuados por equivalentes não acentuados
        $fileName = iconv('UTF-8', 'ASCII//TRANSLIT', $fileName);

        // Converter para minúsculas
        $fileName = strtolower($fileName);

        // Substituir qualquer caractere que não seja alfanumérico por '_'
        $fileName = preg_replace('/[^a-z0-9]/', '_', $fileName);

        // Remover underscores extras
        $fileName = preg_replace('/_+/', '_', $fileName);

        // Remover underscores no início ou fim do nome
        return trim($fileName, '_');
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
