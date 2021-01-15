<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) 2015 ATM Consulting <support@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * \file    class/actions_changeinvoicethirdparty.class.php
 * \ingroup changeinvoicethirdparty
 * \brief   This file is an example hook overload class file
 *          Put some comments here
 */

/**
 * Class Actionschangeinvoicethirdparty
 */
class Actionschangeinvoicethirdparty
{
	/**
	 * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var string String displayed by executeHook() immediately after return
	 */
	public $resprints;

	/**
	 * @var array Errors
	 */
	public $errors = array();

	/**
	 * Constructor
	 */
	public function __construct()
	{
	}

	/**
	 * Overloading the doActions function : replacing the parent's function with the one below
	 *
	 * @param   array()         $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    &$object        The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          &$action        Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $conf;

		$error = 0; // Error counter

		/*print_r($parameters);
		echo "action: " . $action;
		print_r($object);*/
		$TContext = explode(':', $parameters['context']);
		$context = $this->_isInContext($TContext, array('invoicecard', 'ordercard', 'expeditioncard'));
		if ($context)
		{
			$socid=GETPOST('socid');
			$object->fetch($object->id);
			if (!empty($socid)) {
				// update the object's fk_soc in the database
				$object->setValueFrom('fk_soc', $socid);
				// update the loaded object in memory so that we don't have to reload the page again
				$object->socid = $socid;
				$object->fetch_thirdparty();

				if (empty($conf->global->MAIN_DISABLE_PDF_AUTOUPDATE))
				{
					$outputlangs = $langs;
					$newlang = '';
					if ($conf->global->MAIN_MULTILANGS && empty($newlang) && GETPOST('lang_id')) $newlang = GETPOST('lang_id','alpha');
					if ($conf->global->MAIN_MULTILANGS && empty($newlang))	$newlang = $object->thirdparty->default_lang;
					if (! empty($newlang)) {
						$outputlangs = new Translate("", $conf);
						$outputlangs->setDefaultLang($newlang);
					}
					$object->fetch($object->id); // Reload to get new records

					$object->generateDocument($object->modelpdf, $outputlangs);
				}

			}
		}

		if (! $error)
		{
//			$this->results = array('' => '');
//			$this->resprints = '';
			return 0; // or return 1 to replace standard code
		}
		else
		{
			$this->errors[] = 'UnableToChangeThirdParty';
			return -1;
		}
	}


	/**
	 * formConfirm Method Hook Call
	 *
	 * @param string[] $parameters parameters
	 * @param CommonObject $object Object to use hooks on
	 * @param string $action Action code on calling page ('create', 'edit', 'view', 'add', 'update', 'delete'...)
	 * @param HookManager $hookmanager class instance
	 * @return int Hook status
	 */
	function formConfirm($parameters, &$object, &$action, $hookmanager)
	{
		global $langs, $conf, $user, $db ,$bc;

		$TContext = explode(':', $parameters['context']);

		$context = $this->_isInContext($TContext, array('invoicecard', 'ordercard', 'expeditioncard'));

		if ($context) {
			$idParamName = 'id'; // almost all document types use the 'id' parameter in the URL
			if ($context === 'invoicecard') {
				// invoices are an exception (their ID is referred to as "facid" in the URL)
				$idParamName = 'facid';
			}
			if ($action == 'editthirdparty') {
				$form = new Form($db);
				// Create an array to configure the formconfirm dialog box
				$formquestion = array(
					array(
						'type' => 'other',
						'name' => 'socid',
						'label' => $langs->trans("SelectThirdParty"),
						'value' => $form->select_company($object->socid, 'socid', '(s.client=1 OR s.client=2 OR s.client=3)', 1)
					)
				);
				$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"] . '?' . $idParamName . '=' . $object->id, $langs->trans('SetLinkToAnotherThirdParty'), $langs->trans('SetLinkToAnotherThirdParty', $object->ref), 'confirm_editthirdparty', $formquestion, 'yes', 1);
				$this->resprints = $formconfirm;
				return 0; // or return 1 to replace standard code
			}
		}
	}

	/**
	 * addMoreActionsButtons Method Hook Call
	 *
	 * @param string[] $parameters parameters
	 * @param CommonObject $object Object to use hooks on
	 * @param string $action Action code on calling page ('create', 'edit', 'view', 'add', 'update', 'delete'...)
	 * @param HookManager $hookmanager class instance
	 * @return int Hook status
	 */
	function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager) {
		global $langs, $conf, $user, $db ,$bc;

		$TContext = explode(':', $parameters['context']);
		$context = $this->_isInContext($TContext, array('invoicecard', 'ordercard', 'expeditioncard'));

		/*
		 * Si on est sur une fiche commande, facture ou expédition et que l'utilisateur a le droit `updatethirdparty`,
		 * on ajoute un bouton qui affichera le dialogue de sélection d'un nouveau tiers (cf. $this->formConfirm).
		 */
		if ($context) {
			$idParamName = 'id';
			if ($context === 'invoicecard') {
				$idParamName = 'facid';
			}

			// on affiche le bouton seulement si on est en mode "affichage" (pas édition), en brouillon et qu'on a le droit idoine
			if ($action != 'editthirdparty' && $object->brouillon && $user->rights->changeinvoicethirdparty->updatethirdparty) {
				//$html = '<div class="inline-block divButAction"><a class="butAction" href="' . dol_buildpath('/lead/lead/card.php', 1) . '?action=create&socid=' . $object->id . '">' . $langs->trans('LeadCreate') . '</a></div>';
				$html = '<div class="inline-block divButAction"><a class="butAction" href="' . $_SERVER["PHP_SELF"] . '?action=editthirdparty&amp;' . $idParamName . '=' . $object->id . '">' . $langs->trans('SetLinkToAnotherThirdParty') . '</a></div>';
				$html = str_replace('"', '\"', $html);

				$js= '<script type="text/javascript">'."\n";
				$js.= '	$(document).ready('."\n";
				$js.= '		function () {'."\n";
				$js.= '			$(".tabsAction").append("' . $html . '");'."\n";
				$js.= '		});'."\n";
				$js.= '</script>';
				print $js;
			}
		}
	}

	/**
	 * Returns true if any of the provided contexts to check is in the current context array ($TContext)
	 *
	 * @param string[] $TContext Array of current contexts for hooks
	 * @param string[] $TContextToCheck List of contexts that we want to check
	 * @return string|null The first checked context that was matched, NULL if we are in none of the desired contexts
	 */
	private function _isInContext($TContext, $TContextToCheck)
	{
		foreach ($TContextToCheck as $contextToCheck) {
			if (in_array($contextToCheck, $TContext)) return $contextToCheck;
		}
		return null;
	}
}
