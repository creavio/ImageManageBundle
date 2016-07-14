<?php

namespace Creavio\ImageManageBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ResizeController extends Controller
{
	/**
	 * @Route("/cv_image/{path}", name="cv_image_resize", requirements={"path"=".+"})
	 */
	public function imageAction(Request $request)
	{
		$path = $request->get('path');

		if(!empty($request->get('size'))) {
			list($width, $height) = explode("x", $request->get('size'));
			$type = ($value = $request->get('type')) ? $value : 'resize';

			$path = $this->get('kernel')->getRootDir() . '/../web' . $this->get('creavio.image_manager')->resize($type, $path, $width, $height);
		}

		return new BinaryFileResponse($path);
	}
}