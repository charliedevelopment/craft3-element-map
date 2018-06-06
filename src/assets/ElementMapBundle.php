<?php
/**
 * Element Map plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace charliedev\elementmap\assets;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class ElementMapBundle extends AssetBundle
{
	public function init()
	{
		$this->sourcePath = '@charliedev/elementmap/assets/dist';

		$this->depends = [
			CpAsset::class,
		];

		$this->css = [
			'elementmap.css',
		];

		parent::init();
	}
}