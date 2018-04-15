<?php
declare(strict_types=1);
namespace wcf\system\package\plugin;
use wcf\data\package\installation\plugin\PackageInstallationPluginEditor;
use wcf\data\package\installation\plugin\PackageInstallationPluginList;
use wcf\system\devtools\pip\DevtoolsPipEntryList;
use wcf\system\devtools\pip\IDevtoolsPipEntryList;
use wcf\system\devtools\pip\IGuiPackageInstallationPlugin;
use wcf\system\devtools\pip\TXmlGuiPackageInstallationPlugin;
use wcf\system\form\builder\container\FormContainer;
use wcf\system\form\builder\field\validation\FormFieldValidationError;
use wcf\system\form\builder\field\validation\FormFieldValidator;
use wcf\system\form\builder\field\TextFormField;
use wcf\system\form\builder\IFormDocument;
use wcf\system\WCF;

/**
 * Installs, updates and deletes package installation plugins.
 * 
 * @author	Marcel Werk
 * @copyright	2001-2018 WoltLab GmbH
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package	WoltLabSuite\Core\System\Package\Plugin
 */
class PIPPackageInstallationPlugin extends AbstractXMLPackageInstallationPlugin implements IGuiPackageInstallationPlugin {
	use TXmlGuiPackageInstallationPlugin;
	
	/**
	 * @inheritDoc
	 */
	public $className = PackageInstallationPluginEditor::class;
	
	/**
	 * @inheritDoc
	 */
	public $tagName = 'pip';
	
	/**
	 * @inheritDoc
	 */
	protected function handleDelete(array $items) {
		$sql = "DELETE FROM	wcf".WCF_N."_".$this->tableName."
			WHERE		pluginName = ?
					AND packageID = ?";
		$statement = WCF::getDB()->prepareStatement($sql);
		foreach ($items as $item) {
			$statement->execute([
				$item['attributes']['name'],
				$this->installation->getPackageID()
			]);
		}
	}
	
	/**
	 * @inheritDoc
	 */
	protected function prepareImport(array $data) {
		return [
			'className' => $data['nodeValue'],
			'pluginName' => $data['attributes']['name'],
			'priority' => $this->installation->getPackage()->package == 'com.woltlab.wcf' ? 1 : 0
		];
	}
	
	/**
	 * @see	\wcf\system\package\plugin\IPackageInstallationPlugin::getDefaultFilename()
	 * @since	3.0
	 */
	public static function getDefaultFilename() {
		return 'packageInstallationPlugin.xml';
	}
	
	/**
	 * @inheritDoc
	 */
	protected function findExistingItem(array $data) {
		$sql = "SELECT	*
			FROM	wcf".WCF_N."_".$this->tableName."
			WHERE	pluginName = ?
				AND packageID = ?";
		$parameters = [
			$data['pluginName'],
			$this->installation->getPackageID()
		];
		
		return [
			'sql' => $sql,
			'parameters' => $parameters
		];
	}
	
	/**
	 * @inheritDoc
	 */
	public static function getSyncDependencies() {
		return [];
	}
	
	/**
	 * @inheritDoc
	 * @since	3.2
	 */
	public function addFormFields(IFormDocument $form) {
		/** @var FormContainer $dataContainer */
		$dataContainer = $form->getNodeById('data');
		
		$dataContainer->appendChildren([
			TextFormField::create('pluginName')
				->attribute('data-tag', 'name')
				->label('wcf.acp.pip.pip.pluginName')
				->description('wcf.acp.pip.pip.pluginName.description')
				->required()
				->addValidator(new FormFieldValidator('format', function(TextFormField $formField) {
					if (preg_match('~^[a-z][A-z]+$~', $formField->getValue()) !== 1) {
						$formField->addValidationError(
							new FormFieldValidationError(
								'format',
								'wcf.acp.pip.pip.pluginName.error.format'
							)
						);
					}
				}))
				->addValidator(new FormFieldValidator('uniqueness', function(TextFormField $formField) {
					$pipList = new PackageInstallationPluginList();
					$pipList->getConditionBuilder()->add('pluginName = ?', [$formField->getValue()]);
					
					if ($pipList->countObjects()) {
						$formField->addValidationError(
							new FormFieldValidationError(
								'format',
								'wcf.acp.pip.pip.pluginName.error.notUnique'
							)
						);
					}
				})),
			
			TextFormField::create('className')
				->attribute('data-tag', '__value')
				->label('wcf.acp.pip.pip.className')
				->description('wcf.acp.pip.pip.className.description')
				->required()
				->addValidator(new FormFieldValidator('noLeadingBackslash', function(TextFormField $formField) {
					if (substr($formField->getValue(), 0, 1) === '\\') {
						$formField->addValidationError(
							new FormFieldValidationError(
								'leadingBackslash',
								'wcf.acp.pip.pip.className.error.leadingBackslash'
							)
						);
					}
				}))
				->addValidator(new FormFieldValidator('classExists', function(TextFormField $formField) {
					if (!class_exists($formField->getValue())) {
						$formField->addValidationError(
							new FormFieldValidationError(
								'nonExistent',
								'wcf.acp.pip.pip.className.error.nonExistent'
							)
						);
					}
				}))
				->addValidator(new FormFieldValidator('implementsInterface', function(TextFormField $formField) {
					if (!is_subclass_of($formField->getValue(), IPackageInstallationPlugin::class)) {
						$formField->addValidationError(
							new FormFieldValidationError(
								'interface',
								'wcf.acp.pip.pip.className.error.interface'
							)
						);
					}
				}))
				->addValidator(new FormFieldValidator('isInstantiable', function(TextFormField $formField) {
					$reflection = new \ReflectionClass($formField->getValue());
					if (!$reflection->isInstantiable()) {
						$formField->addValidationError(
							new FormFieldValidationError(
								'interface',
								'wcf.acp.pip.pip.className.error.isInstantiable'
							)
						);
					}
				}))
		]);
	}
	
	/**
	 * @inheritDoc
	 * @since	3.2
	 */
	protected function getElementData(\DOMElement $element): array {
		return [
			'className' => $element->nodeValue,
			'pluginName' => $element->getAttribute('name'),
			'priority' => $this->installation->getPackage()->package == 'com.woltlab.wcf' ? 1 : 0
		];
	}
	
	/**
	 * @inheritDoc
	 * @since	3.2
	 */
	public function getElementIdentifier(\DOMElement $element): string {
		return $element->getAttribute('name');
	}
	
	/**
	 * @inheritDoc
	 * @since	3.2
	 */
	public function getEntryList(): IDevtoolsPipEntryList {
		$xml = $this->getProjectXml();
		$xpath = $xml->xpath();
		
		$entryList = new DevtoolsPipEntryList();
		$entryList->setKeys([
			'pluginName' => 'wcf.acp.pip.pip.pluginName',
			'className' => 'wcf.acp.pip.pip.className'
		]);
		
		/** @var \DOMElement $languageItem */
		foreach ($this->getImportElements($xpath) as $element) {
			$entryList->addEntry($this->getElementIdentifier($element), [
				'className' => $element->nodeValue,
				'pluginName' => $element->getAttribute('name')
			]);
		}
		
		return $entryList;
	}
	
	/**
	 * @inheritDoc
	 * @since	3.2
	 */
	protected function sortDocument(\DOMDocument $document) {
		$this->sortImportDelete($document);
		
		$compareFunction = function(\DOMElement $element1, \DOMElement $element2) {
			return strcmp($element1->getAttribute('name'), $element2->getAttribute('name'));
		};
		
		$this->sortChildNodes($document->getElementsByTagName('import'), $compareFunction);
		$this->sortChildNodes($document->getElementsByTagName('delete'), $compareFunction);
	}
	
	/**
	 * @inheritDoc
	 * @since	3.2
	 */
	protected function writeEntry(\DOMDocument $document, IFormDocument $form): \DOMElement {
		/** @var TextFormField $className */
		$className = $form->getNodeById('className');
		/** @var TextFormField $pluginName */
		$pluginName = $form->getNodeById('pluginName');
		
		$pip = $document->createElement('pip', $className->getSaveValue());
		$pip->setAttribute('name', $pluginName->getSaveValue());
		
		$document->getElementsByTagName('import')->item(0)->appendChild($pip);
		
		return $pip;
	}
}
