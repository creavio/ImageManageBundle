<?php

namespace Creavio\ImageManageBundle\Twig\Extension;

use Creavio\ImageManageBundle\Util\ImageManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class ImageManageExtension extends \Twig_Extension
{
	/**
	 * @var ContainerInterface
	 */
	private $container;

	/**
	 * @param ContainerInterface $container
	 */
	public function __construct(KernelInterface $kernel)
	{
		$this->container = $kernel->getContainer();
	}

	/**
	 * @return array
	 */
	public function getFunctions()
	{
		return [];
	}

	/**
	 * @return array
	 */
	public function getFilters()
	{
		return [
			new \Twig_SimpleFilter('image_resize', [$this, 'getImageManager'])
		];
	}

	/**
	 * @param string $file
	 * @param string $type
	 * @param int $width
	 * @param int $height
	 * @return ImageManager
	 */
	public function getImageManager($file, $type = 'resize', $width = null, $height = null)
	{
		return $this->container->get('creavio.image_manager')->resize($type, $file, $width, $height);
	}

	/**
	 * Returns the name of the extension.
	 *
	 * @return string The extension name
	 */
	public function getName()
	{
		return 'creavio_image';
	}
}