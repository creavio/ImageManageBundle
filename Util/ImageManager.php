<?php
namespace Creavio\ImageManageBundle\Util;

use Symfony\Component\DependencyInjection\ContainerInterface;

class ImageManager
{
	const WEB_FOLDER = 'thumbs';
	/**
	 * @var ContainerInterface
	 */
	private $container;

	/**
	 * @param ContainerInterface $container
	 */
	public function __construct(ContainerInterface $container)
	{
		$this->container = $container;
	}

	/**
	 * @param $type
	 * @param $file
	 * @param int $width
	 * @param int $height
	 * @return string
	 * @throws \Exception
	 */
	public function resize($type, $file, $width = 0, $height = 0)
	{
		$file = $this->container->get('kernel')->getRootDir() . '/../web' . $this->fileCleanUp($file);

		list($outputName, $output) = $this->generateNewFileName($file, $type, $width, $height);

		// When resized object exists dont generate it again, just send output
		if(file_exists($output)) {
			return $outputName;
		}

		if($width === null & $height === null) {
			// TODO Get Width, height and Mode from config
			// TODO get versions from config File like small => resize 100x100
			$mode = 'resize';
		} else {
			$mode = $type;
		}

		// Check if File exists on file system
		if(!file_exists($file)) {
			throw new \Exception('File does not exists');
		}

		// Get original size from image
		list($originalWidth, $originalHeight, $imageType) = getimagesize($file);

		// Get Final image sizes
		switch($mode) {
			case 'fill':
				$finalWidth = $width;
				$finalHeight = $height;
				break;
			default:
				list($finalWidth, $finalHeight) = $this->getProportionalSize($width, $height, $originalWidth, $originalHeight);
		}

		switch($imageType) {
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

		// Resize magic
		$imageResized = imagecreatetruecolor($finalWidth, $finalHeight);

		// Handle Transparency
		if(in_array($imageType, [IMAGETYPE_PNG, IMAGETYPE_GIF])) {
			$transparency = imagecolortransparent($image);
			$palletSize = imagecolorstotal($image);

			if($transparency >= 0 && $transparency < $palletSize) {
				$transparentColor = imagecolorsforindex($image, $transparency);
				$transparency = imagecolorallocate($imageResized, $transparentColor['red'], $transparentColor['green'], $transparentColor['blue']);
				imagefill($imageResized, 0, 0, $transparency);
				imagecolortransparent($imageResized, $transparency);
			} else if($imageType == IMAGETYPE_PNG) {
				imagealphablending($imageResized, false);
				$color = imagecolorallocatealpha($imageResized, 0, 0, 0, 127);
				imagefill($imageResized, 0, 0, $color);
				imagesavealpha($imageResized, true);
			}
		}

		// Generate resized file
		imagecopyresampled($imageResized, $image, 0, 0, 0, 0, $finalWidth, $finalHeight, $originalWidth, $originalHeight);

		// Check if web folder exists if not create it
		$completeFolder = $this->container->get('kernel')->getRootDir() . '/../web/' . self::WEB_FOLDER;
		if(!is_dir($completeFolder)) {
			mkdir($completeFolder, 0755);
		}


		switch($imageType) {
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


		return $outputName;
	}

	/**
	 * @param $targetWidth
	 * @param $targetHeight
	 * @param $originalWidth
	 * @param $originalHeight
	 * @return array
	 */
	private function getProportionalSize($targetWidth, $targetHeight, $originalWidth, $originalHeight)
	{
		// Get resize factor
		if($targetWidth === 0) {
			$factor = $targetHeight / $originalHeight;
		} else if($targetHeight === 0) {
			$factor = $targetWidth / $originalWidth;
		} else {
			$factor = min(($targetWidth / $originalWidth), ($targetHeight / $originalHeight));
		}

		// Get final sizes from factor
		return $this->getSizeFromFactor($factor, $originalWidth, $originalHeight);
	}

	/**
	 * @param $factor
	 * @param $originalWidth
	 * @param $originalHeight
	 * @return array
	 */
	private function getSizeFromFactor($factor, $originalWidth, $originalHeight)
	{
		// Get final sizes from factor
		$finalWidth = round($originalWidth * $factor);
		$finalHeight = round($originalHeight * $factor);

		return [$finalWidth, $finalHeight];
	}

	/**
	 * @param $originalFile
	 * @param $type
	 * @param $width
	 * @param $height
	 * @return string
	 */
	private function generateNewFileName($originalFile, $type, $width, $height)
	{
		$pathParts = pathinfo($originalFile);

		$newName = self::WEB_FOLDER .'/' . $pathParts['filename'] . '-' . $type . '-' . $width . 'x' . $height . '.' . $pathParts['extension'];
		$newPath = $this->container->get('kernel')->getRootDir() . '/../web/' . $newName;

		return [$newName, $newPath];
	}

	/**
	 * @param string $file
	 * @return string
     */
	private function fileCleanUp($file)
	{
		return ($file[0] == '/') ? $file : '/' . $file;
	}
}
