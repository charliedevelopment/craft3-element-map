<?php
/**
 * Element Map plugin for Craft 3.0
 * @copyright Copyright Charlie Development
 */

namespace charliedev\elementmap;

use Craft;
use craft\base\Element;
use craft\base\Plugin;
use craft\commerce\elements\Product;
use craft\elements\Asset;
use craft\elements\Category;
use craft\elements\Entry;
use craft\elements\User;
use craft\events\RegisterElementTableAttributesEvent;
use craft\events\SetElementTableAttributeHtmlEvent;

use yii\base\Event;

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

		// Render element maps within the appropriate template hooks.
		Craft::$app->getView()->hook('cp.entries.edit.details', [$this, 'renderEntryElementMap']);
		Craft::$app->getView()->hook('cp.categories.edit.details', [$this, 'renderCategoryElementMap']);
		Craft::$app->getView()->hook('cp.users.edit.details', [$this, 'renderUserElementMap']);
		Craft::$app->getView()->hook('cp.commerce.product.edit.details', [$this, 'renderProductElementMap']);

		// Allow some elements to have map data shown in their overview tables.
		Event::on(Asset::class, Element::EVENT_REGISTER_TABLE_ATTRIBUTES, [$this, 'registerTableAttributes']);
		Event::on(Asset::class, Element::EVENT_SET_TABLE_ATTRIBUTE_HTML, [$this, 'getTableAttributeHtml']);
		Event::on(Category::class, Element::EVENT_REGISTER_TABLE_ATTRIBUTES, [$this, 'registerTableAttributes']);
		Event::on(Category::class, Element::EVENT_SET_TABLE_ATTRIBUTE_HTML, [$this, 'getTableAttributeHtml']);
		Event::on(Entry::class, Element::EVENT_REGISTER_TABLE_ATTRIBUTES, [$this, 'registerTableAttributes']);
		Event::on(Entry::class, Element::EVENT_SET_TABLE_ATTRIBUTE_HTML, [$this, 'getTableAttributeHtml']);
		Event::on(User::class, Element::EVENT_REGISTER_TABLE_ATTRIBUTES, [$this, 'registerTableAttributes']);
		Event::on(User::class, Element::EVENT_SET_TABLE_ATTRIBUTE_HTML, [$this, 'getTableAttributeHtml']);
		Event::on(Product::class, Element::EVENT_REGISTER_TABLE_ATTRIBUTES, [$this, 'registerTableAttributes']);
		Event::on(Product::class, Element::EVENT_SET_TABLE_ATTRIBUTE_HTML, [$this, 'getTableAttributeHtml']);
	}

	/**
	 * Handler for the Element::EVENT_REGISTER_TABLE_ATTRIBUTES event.
	 */
	public function registerTableAttributes(RegisterElementTableAttributesEvent $event)
	{
		$event->tableAttributes['elementMap_incomingReferenceCount'] = ['label' => Craft::t('element-map', 'References From (Count)')];
		$event->tableAttributes['elementMap_outgoingReferenceCount'] = ['label' => Craft::t('element-map', 'References To (Count)')];
		$event->tableAttributes['elementMap_incomingReferences'] = ['label' => Craft::t('element-map', 'References From')];
		$event->tableAttributes['elementMap_outgoingReferences'] = ['label' => Craft::t('element-map', 'References To')];
	}

	/**
	 * Handler for the Element::EVENT_SET_TABLE_ATTRIBUTE_HTML event.
	 */
	public function getTableAttributeHtml(SetElementTableAttributeHtmlEvent $event)
	{
		if ($event->attribute == 'elementMap_incomingReferenceCount') {
			$event->handled = true;
			$entry = $event->sender;
			$elements = $this->renderer->getIncomingElements($entry->id, $entry->site->id);
			$event->html = Craft::$app->view->renderTemplate('element-map/_table', ['elements' => count($elements)]);
		} else if ($event->attribute == 'elementMap_outgoingReferenceCount') {
			$event->handled = true;
			$entry = $event->sender;
			$elements = $this->renderer->getOutgoingElements($entry->id, $entry->site->id);
			$event->html = Craft::$app->view->renderTemplate('element-map/_table', ['elements' => count($elements)]);
		} else if ($event->attribute == 'elementMap_incomingReferences') {
			$event->handled = true;
			$entry = $event->sender;
			$elements = $this->renderer->getIncomingElements($entry->id, $entry->site->id);
			$event->html = Craft::$app->view->renderTemplate('element-map/_table', ['elements' => $elements]);
		} else if ($event->attribute == 'elementMap_outgoingReferences') {
			$event->handled = true;
			$entry = $event->sender;
			$elements = $this->renderer->getOutgoingElements($entry->id, $entry->site->id);
			$event->html = Craft::$app->view->renderTemplate('element-map/_table', ['elements' => $elements]);
		}
	}

	/**
	 * Renders the element map for an entry within the entry editor, given the current Twig context.
	 * @param array $context The incoming Twig context.
	 */
	public function renderEntryElementMap(array &$context)
	{
		$map = $this->renderer->getElementMap($context['entry']['id'], $context['site']['id']);
		return $this->renderMap($map);
	}

	/**
	 * Renders the element map for a category within the category editor, given the current Twig context.
	 * @param array $context The incoming Twig context.
	 */
	public function renderCategoryElementMap(array &$context)
	{
		$map = $this->renderer->getElementMap($context['category']['id'], $context['site']['id']);
		return $this->renderMap($map);
	}

	/**
	 * Renders the element map for a user within the user editor, given the current Twig context.
	 * @param array $context The incoming Twig context.
	 */
	public function renderUserElementMap(array &$context)
	{
		$map = $this->renderer->getElementMap($context['user']['id'], Craft::$app->getSites()->getPrimarySite()->id);
		return $this->renderMap($map);
	}

	/**
	 * Renders the element map for a product within the product editor, given the current Twig context.
	 * @param array $context The incoming Twig context.
	 */
	public function renderProductElementMap(array &$context)
	{
		$map = $this->renderer->getElementMap($context['product']['id'], $context['site']['id']);
		return $this->renderMap($map);
	}

	/**
	 * Renders an underlying incoming/outgoing element map.
	 * @param array $map The map data to render.
	 */
	private function renderMap($map)
	{
		if ($map) {
			return Craft::$app->view->renderTemplate('element-map/_map', ['map' => $map]);
		}
	}
}
