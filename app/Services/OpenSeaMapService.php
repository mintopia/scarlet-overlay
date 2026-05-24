<?php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;

class OpenSeaMapService
{
    public function __construct(protected ImageManager $imageManager)
    {
        if (Storage::directoryMissing('openseamap')) {
            Storage::createDirectory('openseamap');
        }
        if (Storage::directoryMissing('openseamap-dark')) {
            Storage::createDirectory('openseamap-dark');
        }
    }
    public function getTile(int $z, int $x, int $y): ?string
    {
        $filename = "openseamap/{$z}.{$x}.{$y}.png";
        if (Storage::exists($filename)) {
            return $filename;
        }

        return $this->makeTile($filename, $z, $x, $y);
    }

    protected function makeTile(string $filename, int $z, int $x, int $y): ?string
    {
        $background = $this->getTileImage('openstreetmap', 'https://tile.openstreetmap.org', $z, $x, $y);
        if ($background === null) {
            return null;
        }
        $overlay = $this->getTileImage('seamark', 'https://tiles.openseamap.org/seamark', $z, $x, $y);
        if ($overlay === null) {
            $background->save(Storage::path($filename));
            return $filename;
        }

        $background->place($overlay);
        $background->save(Storage::path($filename));
        return $filename;
    }

    public function getDarkTile(int $z, int $x, int $y): ?string
    {
        $filename = "openseamap-dark/{$z}.{$x}.{$y}.png";
        if (Storage::exists($filename)) {
            return $filename;
        }

        return $this->makeDarkTile($filename, $z, $x, $y);
    }

    protected function makeDarkTile(string $filename, int $z, int $x, int $y): ?string
    {
        $background = $this->getTileImage('cartodb-dark', 'https://basemaps.cartocdn.com/dark_all', $z, $x, $y);
        if ($background === null) {
            return null;
        }

        $rawOverlay = $this->getTileImage('seamark', 'https://tiles.openseamap.org/seamark', $z, $x, $y);
        if ($rawOverlay === null) {
            $background->save(Storage::path($filename));
            return $filename;
        }

        $overlay = $this->lightenForDarkBase(Storage::path("seamark/{$z}.{$x}.{$y}.png")) ?? $rawOverlay;
        $background->place($overlay);
        $background->save(Storage::path($filename));
        return $filename;
    }

    protected function lightenForDarkBase(string $path): ?ImageInterface
    {
        $gd = @imagecreatefrompng($path);
        if (!$gd) return null;

        imagealphablending($gd, false);
        imagesavealpha($gd, true);

        $w = imagesx($gd);
        $h = imagesy($gd);

        for ($px = 0; $px < $w; $px++) {
            for ($py = 0; $py < $h; $py++) {
                $rgba = imagecolorat($gd, $px, $py);
                $alpha = ($rgba >> 24) & 0x7F;
                if ($alpha > 120) continue;

                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;

                $brightness = ($r + $g + $b) / 3;
                $saturation = max($r, $g, $b) - min($r, $g, $b);

                if ($brightness < 80 && $saturation < 40) {
                    $color = imagecolorallocatealpha($gd, 220, 220, 220, $alpha);
                    imagesetpixel($gd, $px, $py, $color);
                }
            }
        }

        ob_start();
        imagepng($gd);
        $data = ob_get_clean();
        imagedestroy($gd);

        return $this->imageManager->read($data);
    }

    protected function getTileImage(string $prefix, string $url, int $z, int $x, int $y): ?ImageInterface
    {
        $filename = "{$prefix}/{$z}.{$x}.{$y}.png";
        if (Storage::missing($filename)) {
            Log::info("Downloading {$url}/{$z}/{$x}/{$y}");
            $response = Http::withHeader('User-Agent', 'Scarlet Sailing Map Overlay; jess@mintopia.net')
                ->get("{$url}/{$z}/{$x}/{$y}.png");
            if ($response->failed()) {
                Log::warning("Failed to download {$url}/{$z}/{$x}/{$y}: {$response->getStatusCode()}");
                return null;
            }
            Storage::put($filename, $response->body());
        } else {
            Log::info("Loading {$prefix} tile {$z}/{$x}/{$y} from cache");
        }

        return $this->imageManager->read(Storage::path($filename));
    }
}
