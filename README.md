# Module ChangeInvoiceThirdParty

Ce module permet de modifier le tiers d’une commande, facture ou expédition en brouillon.

Il faut avoir le droit idoine (*Modification d'un client sur une facture, 
une commande ou une livraison*) pour pouvoir voir le bouton *Lier vers un
autre tiers*.

## Notes d’implémentation
Le module utilise exclusivement les hooks :
* addMoreActionsButtons (pour afficher un bouton sur les fiches en brouillon si le
  droit est défini)
* formConfirm (pour afficher un dialogue quand on clique sur le bouton)
* doActions (pour changer le tiers lorsqu'on confirme l'action)
