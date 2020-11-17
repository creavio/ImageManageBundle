<?php

namespace Creavio\ImageManageBundle\Util;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class ImageManager
{
	/**
	 *
	 */
	private const WEB_FOLDER = 'thumbs';

	/**
	 * @var ContainerInterface
	 */
	private $container;

	/**
	 * @param KernelInterface $kernel
	 */
	public function __construct(KernelInterface $kernel)
	{
		$this->container = $kernel->getContainer();
	}

	/**
	 * @param string $type
	 * @param string $file
	 * @param int $width
	 * @param int $height
	 *
	 * @return string
	 *
	 * @throws \Exception
	 */
	public function resize(string $type, string $file, int $width = 0, int $height = 0): string
	{
		$file = $this->container->get('kernel')->getRootDir() . '/../web' . $this->fileCleanUp($file);

		list($outputName, $output) = $this->generateNewFileName($file, $type, $width, $height);

		// When resized object exists dont generate it again, just send output
		if (file_exists($output)) {
			return $outputName;
		}

		if ($width === null & $height === null) {
			// TODO Get Width, height and Mode from config
			// TODO get versions from config File like small => resize 100x100
			$mode = 'resize';
		} else {
			$mode = $type;
		}

		// Get original size from image
		[$originalWidth, $originalHeight, $imageType, $image] = $this->createImage($file);

		// Get Final image sizes
		switch ($mode) {
			case 'fill':
				$finalWidth = $width;
				$finalHeight = $height;
				break;
			default:
				list($finalWidth, $finalHeight) = $this->getProportionalSize($width, $height, $originalWidth, $originalHeight);
		}

		$imageResized = $this->getResizeImage($finalWidth, $finalHeight, $image, $imageType);

		// Generate resized file
		imagecopyresampled($imageResized, $image, 0, 0, 0, 0, $finalWidth, $finalHeight, $originalWidth, $originalHeight);

		$this->writeImage($image, $imageResized, $imageType, $output, $finalWidth, $finalHeight, $originalWidth, $originalHeight);

		return $outputName;
	}

	/**
	 * @param string $file
	 * @param int $width
	 * @param int $height
	 * @param array $properties
	 *
	 * @return string
	 *
	 * @throws \Exception
	 */
	public function resizeFromProperties(string $file, int $width, int $height, array $properties): string
	{
		$scale = array_key_exists('size', $properties) ? $properties['size'] / 100 : 1;
		$positionScaleX = array_key_exists('positionX', $properties) ? $properties['positionX'] / 100 : 0.5;
		$positionScaleY = array_key_exists('positionY', $properties) ? $properties['positionY'] / 100 : 0.5;

		$identifier = "property-{$scale}-{$positionScaleX}-{$positionScaleY}";
		[$outputName, $output] = $this->generateNewFileName($file, str_replace('.', '-', $identifier), $width, $height);

		if (file_exists($output)) {
			return $outputName;
		}

		[$originalWidth, $originalHeight, $imageType, $image] = $this->createImage($file);
		$imageResized = $this->getResizeImage($width, $height, $image, $imageType);

		[$finalWidth, $finalHeight] = $this->getProportionalSize(max([$width, $height]), max([$width, $height]), $originalWidth, $originalHeight);

		$finalWidth = $finalWidth * $scale;
		$finalHeight = $finalHeight * $scale;

		$positionScaleX = ($finalWidth - $width) * $positionScaleX * -1;
		$positionScaleY = ($finalHeight - $height) * $positionScaleY * -1;

		// Generate resized file
		imagecopyresampled($imageResized, $image, $positionScaleX, $positionScaleY,0, 0, $finalWidth, $finalHeight, $originalWidth, $originalHeight);

		$this->writeImage($image, $imageResized, $imageType, $output, $width, $height, $originalWidth, $originalHeight);


		return $outputName;
	}

	/**
	 * @param int $targetWidth
	 * @param int $targetHeight
	 * @param int $originalWidth
	 * @param int $originalHeight
	 *
	 * @return array
	 */
	private function getProportionalSize(int $targetWidth, int $targetHeight, int $originalWidth, int $originalHeight): array
	{
		// Get resize factor
		if ($targetWidth === 0) {
			$factor = $targetHeight / $originalHeight;
		} else if ($targetHeight === 0) {
			$factor = $targetWidth / $originalWidth;
		} else {
			$factor = min(($targetWidth / $originalWidth), ($targetHeight / $originalHeight));
		}

		// Get final sizes from factor
		return $this->getSizeFromFactor($factor, $originalWidth, $originalHeight);
	}

	/**
	 * @param float $factor
	 * @param int $originalWidth
	 * @param int $originalHeight
	 * @return array
	 */
	private function getSizeFromFactor(float $factor, int $originalWidth, int $originalHeight): array
	{
		// Get final sizes from factor
		$finalWidth = round($originalWidth * $factor);
		$finalHeight = round($originalHeight * $factor);

		return [$finalWidth, $finalHeight];
	}

	/**
	 * @param string $originalFile
	 * @param string $identifier
	 * @param int $width
	 * @param int $height
	 *
	 * @return array
	 */
	private function generateNewFileName(string $originalFile, string $identifier, int $width, int $height): array
	{
		$pathParts = pathinfo($originalFile);

		$newName = self::WEB_FOLDER . '/' . $pathParts['filename'] . '-' . $identifier . '-' . $width . 'x' . $height . '.' . $pathParts['extension'];
		$newPath = $this->container->get('kernel')->getRootDir() . '/../web/' . $newName;

		return [$newName, $newPath];
	}

	/**
	 * @param string $file
	 *
	 * @return array
	 *
	 * @throws \Exception
	 */
	private function createImage(string $file): array
	{
		// Check if File exists on file system
		if (!file_exists($file)) {
			throw new \Exception('File does not exists');
		}

		// Get original size from image
		[$originalWidth, $originalHeight, $imageType] = getimagesize($file);

		switch ($imageType) {
			case IMAGETYPE_JPEG:
				$image = imagecreatefromjpeg($file);
				break;
			case IMAGETYPE_GIF:
				$image = imagecreatefromgif($file);
				break;
			case IMAGETYPE_PNG:
				$image = imagecreatefrompng($file);
				break;
			default:
				throw new \Exception('Image type is not supported');
		}

		return [$originalWidth, $originalHeight, $imageType, $image];
	}

	/**
	 * @param int $finalWidth
	 * @param int $finalHeight
	 * @param $image
	 * @param $imageType
	 * @return resource
	 */
	private function getResizeImage(int $finalWidth, int $finalHeight, $image, $imageType)
	{
		$imageResized = imagecreatetruecolor($finalWidth, $finalHeight);

		// Handle Transparency
		if (in_array($imageType, [IMAGETYPE_PNG, IMAGETYPE_GIF])) {
			$transparency = imagecolortransparent($image);
			$palletSize = imagecolorstotal($image);

			if ($transparency >= 0 && $transparency < $palletSize) {
				$transparentColor = imagecolorsforindex($image, $transparency);
				$transparency = imagecolorallocate($imageResized, $transparentColor['red'], $transparentColor['green'], $transparentColor['blue']);
				imagefill($imageResized, 0, 0, $transparency);
				imagecolortransparent($imageResized, $transparency);
			} else if ($imageType == IMAGETYPE_PNG) {
				imagealphablending($imageResized, false);
				$color = imagecolorallocatealpha($imageResized, 0, 0, 0, 127);
				imagefill($imageResized, 0, 0, $color);
				imagesavealpha($imageResized, true);
			}
		}

		return $imageResized;
	}

	/**
	 * @param $image
	 * @param $imageType
	 * @param int $finalWidth
	 * @param int $finalHeight
	 * @param int $originalWidth
	 * @param int $originalHeight
	 *
	 * @throws \Exception
	 */
	private function writeImage($image, $imageResized, $imageType, $output, int $finalWidth, int $finalHeight, int $originalWidth, int $originalHeight)
	{
		// Check if web folder exists if not create it
		$completeFolder = $this->container->get('kernel')->getRootDir() . '/../web/' . self::WEB_FOLDER;
		if (!is_dir($completeFolder)) {
			mkdir($completeFolder, 0755);
		}

		switch ($imageType) {
			case IMAGETYPE_GIF:
				imagegif($imageResized, $output);
				break;
			case IMAGETYPE_JPEG:
				imagejpeg($imageResized, $output, 100);
				break;
			case IMAGETYPE_PNG:
				imagepng($imageResized, $output, 0);
				break;
			default:
				throw new \Exception('Image type is not supported');
		}
	}

	/**
	 * @param string $file
	 *
	 * @return string
	 */
	private function fileCleanUp(string $file): string
	{
		return ($file[0] == '/') ? $file : '/' . $file;
	}
}
