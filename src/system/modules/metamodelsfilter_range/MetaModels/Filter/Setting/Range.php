<?php

/**
 * The MetaModels extension allows the creation of multiple collections of custom items,
 * each with its own unique set of selectable attributes, with attribute extendability.
 * The Front-End modules allow you to build powerful listing and filtering of the
 * data in each collection.
 *
 * PHP version 5
 * @package    MetaModels
 * @subpackage FilterRange
 * @author     Christian de la Haye <service@delahaye.de>
 * @copyright  The MetaModels team.
 * @license    LGPL.
 * @filesource
 */

namespace MetaModels\Filter\Setting;

use MetaModels\Filter\IFilter;
use MetaModels\Filter\Rules\SimpleQuery;
use MetaModels\Filter\Rules\StaticIdList;
use MetaModels\FrontendIntegration\FrontendFilterOptions;
use MetaModels\Helper\ContaoController;

/**
 * Filter "value in range of 2 fields" for FE-filtering, based on filters by the meta models team.
 *
 * @package    MetaModels
 * @subpackage FilterRange
 * @author     Christian de la Haye <service@delahaye.de>
 */
class Range extends Simple
{
	/**
	 * {@inheritdoc}
	 */
	protected function getParamName()
	{
		if ($this->get('urlparam'))
		{
			return $this->get('urlparam');
		}

		$objAttribute = $this->getMetaModel()->getAttributeById($this->get('attr_id'));
		if ($objAttribute)
		{
			return $objAttribute->getColName();
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function prepareRules(IFilter $objFilter, $arrFilterUrl)
	{
		$objMetaModel = $this->getMetaModel();
		$objAttribute = $objMetaModel->getAttributeById($this->get('attr_id'));
		$objAttribute2 = $objMetaModel->getAttributeById($this->get('attr_id2'));

		if (!$objAttribute2)
		{
			$objAttribute2 = $objAttribute;
		}


		$strParamName = $this->getParamName();
		$strParamValue = $arrFilterUrl[$strParamName];
		$strMore = $this->get('moreequal') ? '>=' : '>';
		$strLess = $this->get('lessequal') ? '<=' : '<';


		if (array_key_exists($strParamName, $arrFilterUrl) && !empty($arrFilterUrl[$strParamName]))
		{

			
			if (is_array($arrFilterUrl[$strParamName]))
			{
				$arrParamValue = $arrFilterUrl[$strParamName];
			} else {
				// TODO: still unsure if double underscore is such a wise idea.
				$arrParamValue = explode('__', $arrFilterUrl[$strParamName]);
			}

			// if attr_type==timestamp ? transform datestring to unixtimestamp
			if ($objAttribute->get('type') == 'timestamp')
			{
				// TODO: make Contao Date()-Class useable, $objData is still empty (why?)
				$objDate = ContaoController::getInstance()->import('Date');

				foreach($arrParamValue AS $dateStrKey => $dateStrVal) 
				{
					

					$objDate  = new \Date($dateStrVal, $GLOBALS['TL_CONFIG'][$rgxp . 'Format']);
					$arrParamValue[$dateStrKey] = $objDate->tstamp;
		
				}
			}


		}

		if ($objAttribute && $objAttribute2 && $strParamName && $strParamValue)
		{
			$objFilter->addFilterRule(new SimpleQuery(
				sprintf(
					'SELECT id FROM %s WHERE (?%s%s AND ?%s%s)',
					$this->getMetaModel()->getTableName(),
					$strLess,
					$objAttribute2->getColName(),
					$strMore,
					$objAttribute->getColName()
				), array($arrParamValue[0],$arrParamValue[0])
			));
			return;
		}

		$objFilter->addFilterRule(new StaticIdList(NULL));
	}

	/**
	 * {@inheritdoc}
	 */
	public function getParameterFilterNames()
	{
		$strLabel = ($this->get('label') ? $this->get('label') : $this->getMetaModel()->getAttributeById($this->get('attr_id'))->getName());

		return array(
			$this->getParamName() => $strLabel
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function getParameterFilterWidgets($arrIds, $arrFilterUrl, $arrJumpTo, FrontendFilterOptions $objFrontendFilterOptions)
	{
		$objAttribute = $this->getMetaModel()->getAttributeById($this->get('attr_id'));


		$arrOptions = $objAttribute->getFilterOptions(($this->get('onlypossible') ? $arrIds : NULL), (bool)$this->get('onlyused'));

		// Remove empty values from list.
		foreach ($arrOptions as $mixKeyOption => $mixOption)
		{
			// Remove html/php tags.
			$mixOption = strip_tags($mixOption);
			$mixOption = trim($mixOption);

			if($mixOption === '' || $mixOption === null)
			{
				unset($arrOptions[$mixKeyOption]);
			}
		}


		$arrLabel = array(
			($this->get('label') ? $this->get('label') : $objAttribute->getName()),
			'GET: '.$this->getParamName()
		);


			// split up our param so the widgets can use it again.
		$strParamName = $this->getParamName(); // test
		$arrMyFilterUrl = $arrFilterUrl; // test => 1__2

		
		// if we have a value, we have to explode it by double underscore to have a valid value which the active checks may cope with.
		if (array_key_exists($strParamName, $arrFilterUrl) && !empty($arrFilterUrl[$strParamName]))
		{
			if (is_array($arrFilterUrl[$strParamName]))
			{
				$arrParamValue = $arrFilterUrl[$strParamName];
			} else {
				// TODO: still unsure if double underscore is such a wise idea.
				$arrParamValue = explode('__', $arrFilterUrl[$strParamName], 2);
			}

			if ($arrParamValue && ($arrParamValue[0] || $arrParamValue[1]))
			{
				$arrMyFilterUrl[$strParamName] = $arrParamValue;
			} else {
				// no values given, clear the array.
				$arrParamValue = NULL;
			}

		}


		$GLOBALS['MM_FILTER_PARAMS'][] = $this->getParamName();


		return array(
			$this->getParamName() => $this->prepareFrontendFilterWidget(
				array
				(
					'label'     => $arrLabel,
					'inputType' => 'text',
					'eval'      => array
					(
						'urlparam'     => $this->getParamName(),
						'template'     => $this->get('template'),
						'helpwizard'   => true
					)
				),
				$arrFilterUrl,
				$arrJumpTo,
				$objFrontendFilterOptions
			)
		);
}

	/**
	 * Retrieve the attributes that are referenced in this filter setting.
	 *
	 * @return array
	 */
	public function getReferencedAttributes()
	{
		$objMetaModel  = $this->getMetaModel();
		$objAttribute  = $objMetaModel->getAttributeById($this->get('attr_id'));
		$objAttribute2 = $objMetaModel->getAttributeById($this->get('attr_id2'));
		$arrResult     = array();

		if ($objAttribute)
		{
			$arrResult[] = $objAttribute->getColName();
		}

		if ($objAttribute2)
		{
			$arrResult[] = $objAttribute2->getColName();
		}

		return $arrResult;
	}
}
