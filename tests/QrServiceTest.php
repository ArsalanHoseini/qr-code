<?php

declare(strict_types=1);

use App\DeviceParser;
use App\QrDesign;
use App\QrService;
use PHPUnit\Framework\TestCase;
use Zxing\QrReader;

final class QrServiceTest extends TestCase
{
    public function testOnlyHttpAndHttpsUrlsAreAccepted(): void
    {
        self::assertTrue(QrService::validateHttpUrl('https://example.com/path?q=1'));
        self::assertTrue(QrService::validateHttpUrl('http://localhost:8080/test'));
        self::assertFalse(QrService::validateHttpUrl('javascript:alert(1)'));
        self::assertFalse(QrService::validateHttpUrl('data:text/html,test'));
        self::assertFalse(QrService::validateHttpUrl('file:///c:/secret'));
    }

    public function testPngAndSvgOutputAreGenerated(): void
    {
        $png = QrService::render('https://example.com', 'png');
        self::assertSame('image/png', $png['mime']);
        self::assertStringStartsWith("\x89PNG", $png['content']);
        $svg = QrService::render('متن فارسی', 'svg');
        self::assertSame('image/svg+xml', $svg['mime']);
        self::assertStringContainsString('<svg', $svg['content']);
        self::assertStringNotContainsString('<script', strtolower($svg['content']));
    }

    public function testDeviceInformationIsCoarse(): void
    {
        [$device, $browser, $os] = DeviceParser::parse('Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 Chrome/124 Mobile');
        self::assertSame('موبایل', $device);
        self::assertSame('Chrome', $browser);
        self::assertSame('Android', $os);
    }

    public function testVisuallyDifferentDesignsProduceValidExports(): void
    {
        $designs = [
            array_replace(QrDesign::defaults(), ['dot_style'=>'dot','finder_style'=>'rounded','finder_dot_style'=>'dot']),
            array_replace(QrDesign::defaults(), ['color_mode'=>'linear','foreground_color'=>'#315ce8','gradient_end_color'=>'#7c3aed','dot_style'=>'extra_rounded']),
            array_replace(QrDesign::defaults(), ['background_mode'=>'transparent','frame_style'=>'branded','frame_text'=>'SCAN ME','error_correction'=>'H']),
        ];
        $hashes = [];
        foreach ($designs as $design) {
            $png = QrService::render('https://example.com/designer', 'png', design: $design);
            $svg = QrService::render('https://example.com/designer', 'svg', design: $design);
            self::assertStringStartsWith("\x89PNG", $png['content']);
            self::assertStringContainsString('<svg', $svg['content']);
            self::assertStringNotContainsString('<script', strtolower($svg['content']));
            $hashes[] = hash('sha256', $png['content']);
        }
        self::assertCount(3, array_unique($hashes));
    }

    public function testSafetyIndicatorWarnsForRiskyDesign(): void
    {
        $safe = QrDesign::safety(QrDesign::defaults(), false);
        self::assertSame('safe', $safe['status']);
        $risky = QrDesign::safety(array_replace(QrDesign::defaults(), ['foreground_color'=>'#eeeeee','background_color'=>'#ffffff','margin'=>16,'logo_size'=>24,'error_correction'=>'L']), true);
        self::assertSame('unsafe', $risky['status']);
    }

    public function testSafeStyledPngsDecodeBackToOriginalPayload(): void
    {
        $payload = 'https://example.com/verified-qr';
        $designs = [
            QrDesign::defaults(),
            array_replace(QrDesign::defaults(), ['dot_style'=>'rounded','finder_style'=>'rounded','finder_dot_style'=>'dot','error_correction'=>'Q']),
            array_replace(QrDesign::defaults(), ['color_mode'=>'linear','foreground_color'=>'#123a70','gradient_end_color'=>'#315ce8','dot_style'=>'extra_rounded','error_correction'=>'H']),
            array_replace(QrDesign::defaults(), ['dot_style'=>'dot','finder_style'=>'outpoint','finder_dot_style'=>'rounded','error_correction'=>'H','margin'=>32]),
        ];
        foreach ($designs as $design) {
            $png = QrService::render($payload, 'png', design: $design);
            $path = tempnam(sys_get_temp_dir(), 'qr-test-');
            file_put_contents($path, $png['content']);
            try { self::assertSame($payload, (new QrReader($path))->text()); }
            finally { @unlink($path); }
        }
    }

    public function testTransparentBackgroundIsActuallyTransparent(): void
    {
        $design = array_replace(QrDesign::defaults(), ['background_mode'=>'transparent']);
        $png = QrService::render('https://example.com/transparent', 'png', design: $design);
        $image = imagecreatefromstring($png['content']);
        self::assertNotFalse($image);
        $rgba = imagecolorat($image, 0, 0);
        self::assertSame(127, ($rgba >> 24) & 0x7F);
        imagedestroy($image);
    }
}
