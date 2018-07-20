<?php
/**
 * Element Map plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace charliedev\elementmap;

use Craft;
use craft\base\Plugin;

/**
 * The main Craft plugin class.
 */
class ElementMap extends Plugin
{

	/**
	 * @inheritdoc
	 * @see craft\base\Plugin
	 */
	public function init()
	{
		parent::init();

		$this->setComponents([
			'renderer' => \charliedev\elementmap\services\Renderer::class,
		]);

		Craft::$app->getView()->hook('cp.entries.edit.details', [$this, 'renderEntryElementMap']);
		Craft::$app->getView()->hook('cp.categories.edit.details', [$this, 'renderCategoryElementMap']);
		Craft::$app->getView()->hook('cp.users.edit.details', [$this, 'renderUserElementMap']);
		Craft::$app->getView()->hook('cp.commerce.product.edit.details', [$this, 'renderProductElementMap']);
	}

	/**
	 * Renders the element map for an entry within the entry editor, given the current Twig context.
	 * @param array $context The incoming Twig context.
	 */
	public function renderEntryElementMap(array &$context)
	{
		if ($context['entry']['id'] != null) {
			return $this->renderer->render($context['entry']['id'], $context['site']['id']);
		}
	}

	/**
	 * Renders the element map for a category within the category editor, given the current Twig context.
	 * @param array $context The incoming Twig context.
	 */
	public function renderCategoryElementMap(array &$context)
	{
		if ($context['category']['id'] != null) {
			return $this->renderer->render($context['category']['id'], $context['site']['id']);
		}
	}

	/**
	 * Renders the element map for a user within the user editor, given the current Twig context.
	 * @param array $context The incoming Twig context.
	 */
	public function renderUserElementMap(array &$context)
	{
		if ($context['user']['id'] != null) {
			return $this->renderer->render($context['user']['id'], Craft::$app->getSites()->getPrimarySite()->id);
		}
	}

	/**
	 * Renders the element map for a product within the product editor, given the current Twig context.
	 * @param array $context The incoming Twig context.
	 */
	public function renderProductElementMap(array &$context)
	{
		if ($context['product']['id'] != null) {
			return $this->renderer->render($context['product']['id'], $context['site']['id']);
		}
	}
}
