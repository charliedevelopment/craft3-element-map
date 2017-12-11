<?php

namespace charliedev\elementmap\services;

use Craft;

use yii\base\Component;

class Renderer extends Component {

	/**
	 * Renders an element map relative to the given element.
	 * @param int $elementid The ID of the element to render the map relative to.
	 */
	public function render(int $elementid)
	{
		// Gather up necessary structure data to render the element map with.
		// TODO

		// Render the actual element map.
		return Craft::$app->view->renderTemplate(
			'element-map/_map',
			[
				// TODO
//				'elements' => $elements
			]);
	}
}
