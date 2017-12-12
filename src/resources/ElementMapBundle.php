<?php
/**
 * Element Map plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace charliedev\elementmap\resources;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class ElementMapBundle extends AssetBundle
{
	public function init()
	{
		$this->sourcePath = '@charliedev/elementmap/resources/dist';

		$this->depends = [
			CpAsset::class,
		];

		$this->css = [
			'elementmap.css',
		];

		parent::init();
	}
}