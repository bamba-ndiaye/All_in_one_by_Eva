# Guide de tests manuels — All in One by Eva

Ce guide sert à vérifier **à la main, dans le navigateur**, que chaque fonctionnalité marche.
Chaque test indique la page à ouvrir, ce qu'il faut faire et le résultat attendu.
Cochez `[x]` quand c'est bon. Si un test échoue, notez la page, ce que vous avez fait et le message affiché (et, si besoin, l'erreur rouge de la console : touche **F12**, onglet « Console »).

> Les tests automatiques (`php artisan test`) vérifient déjà la logique (plus de 500 tests). Ce guide vérifie ce qu'ils ne voient pas : l'affichage, le JavaScript, les boutons, l'enchaînement des écrans.

---

## 0. Préparer

### 0.1 Démarrer le projet

```bash
docker compose up -d
```

Attendez une vingtaine de secondes puis ouvrez **http://localhost:8000**.

### 0.2 Charger le jeu de données de test

```bash
docker compose exec app php artisan db:seed --class=JeuDeTestSeeder
```

La commande peut être relancée sans créer de doublons. Elle est refusée en production.

**Repartir de zéro** (⚠️ efface **toutes** les données de la base locale) :

```bash
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app php artisan db:seed --class=JeuDeTestSeeder
```

### 0.3 Les comptes

| Rôle | E-mail | Mot de passe | Ce qu'il peut faire |
|---|---|---|---|
| Administratrice | `ADMIN_EMAIL` du fichier `.env` | `ADMIN_PASSWORD` du `.env` | Tout |
| Réceptionniste | `reception@test.local` | `Reception-2026` | Clients, rendez-vous, encaisser, anniversaires, voir la fidélité |
| Professionnel(le) | `awa.fall@test.local` | `Coiffeuse-2026` | Seulement **ses** rendez-vous (fiche employé « Awa [Démo] Fall ») |

> Astuce : ouvrez une fenêtre de navigation privée pour être connecté avec deux comptes en même temps.

### 0.4 Les données créées

Toutes les données de test portent la mention **[Démo]**.

**Clientes**

| Nom | Téléphone | Promo WhatsApp | Naissance | État |
|---|---|---|---|---|
| Awa [Démo] Diallo | +221 77 000 01 01 | oui | 12/05/1990 | active |
| Mariama [Démo] Sow | +221 77 000 01 02 | oui | 03/11/1985 | active |
| Fatou [Démo] Ba | +221 76 000 01 03 | non | 28/02/1994 | active |
| Khady [Démo] Seck | +221 78 000 01 04 | oui | — | active |
| Aminata [Démo] Gueye | +221 75 000 01 05 | non | 19/08/2001 | active |
| Ibrahima [Démo] Thiam | +221 77 000 01 06 | non | 25/12/1988 | active |
| Cheikh [Démo] Mbaye | +221 77 000 01 07 | oui | 07/07/2015 | active |
| Rokhaya [Démo] Cissé | +221 77 000 01 08 | non | 15/01/1979 | **archivée** |

**Équipe** : horaires du lundi au vendredi 9 h–13 h et 14 h–18 h, samedi 9 h–14 h, fermé le dimanche.

| Employé | Fonction | Prestations | Commission |
|---|---|---|---|
| Awa [Démo] Fall | Coiffeuse | coupes femme, homme et enfant, tresses | 20 % |
| Mariama [Démo] Diop | Esthéticienne | manucure, pédicure, soin du visage | 1 000 FCFA par prestation |
| Fatou [Démo] Ndiaye | Masseuse | massage relaxant, soin du visage | aucune |

**Prestations** : Coupe homme 3 000 FCFA (30 min), Coupe femme 5 000 FCFA (45 min), Coupe enfant 2 500 FCFA (30 min), Tresses simples 10 000 FCFA (3 h, espace femmes), Massage relaxant 15 000 FCFA (1 h), Manucure simple 5 000 FCFA (45 min), Pédicure simple 6 000 FCFA (1 h), Soin du visage 12 000 FCFA (1 h).
**Forfaits** : Mains et pieds 10 000 FCFA (manucure + pédicure), Détente 24 000 FCFA (massage + soin du visage).

**Rendez-vous** : 2 rendez-vous **terminés il y a 2 jours ouvrés** (Cheikh, coupe enfant, payé en entier par Wave ; Aminata, manucure, payée à moitié en espèces), puis environ 6 rendez-vous **confirmés** dans les 3 prochains jours ouvrés.

**Stock** : 8 produits, dont 3 en stock bas (Cire épilatoire tiède : 0, Dissolvant sans acétone : 1, Après-shampoing karité : 2).
**Dépenses** : 4 dépenses (25 000 aujourd'hui, 18 000, 6 500 et 12 000 FCFA les jours précédents).
**Fidélité** : 1 point par tranche de 1 000 FCFA encaissés ; 4 récompenses (20, 50, 80 et 120 points).
**Modes de paiement** : Espèces, Wave, Orange Money, Carte bancaire, Autre.

---

## 1. Connexion et sécurité

| ✓ | Page | Action | Résultat attendu |
|---|---|---|---|
| [ ] | http://localhost:8000 | Ouvrir sans être connecté | Redirection vers **/connexion** |
| [ ] | /connexion | Se connecter en administratrice | Arrivée sur le tableau de bord « Bonjour … » |
| [ ] | /connexion | Mauvais mot de passe | Message d'erreur, pas de connexion. Même message pour un e-mail inconnu |
| [ ] | /connexion | 5 mauvais mots de passe de suite | La connexion est bloquée un moment |
| [ ] | Menu en bas à gauche | « Se déconnecter » | Retour à /connexion |
| [ ] | /connexion | Se connecter en **réceptionniste** | Le menu ne montre pas Dépenses, Rapports, Utilisateurs, Paramètres |
| [ ] | Taper http://localhost:8000/depenses (réceptionniste) | — | Page **403** (accès refusé) |
| [ ] | /connexion | Se connecter en **professionnel** | Le menu montre seulement Tableau de bord, Rendez-vous, Prestations |

## 2. Tableau de bord — `/`

| ✓ | Compte | Action | Résultat attendu |
|---|---|---|---|
| [ ] | Admin | Ouvrir | 4 cartes en haut (Encaissé, Dépenses, Résultat, Panier moyen) avec petites courbes |
| [ ] | Admin | Passer la souris sur une carte | La carte se soulève légèrement, l'icône devient bordeaux |
| [ ] | Admin | Graphique « Recettes et dépenses » : cliquer 7 jours / 30 jours / 12 mois | La courbe et les totaux au-dessus changent |
| [ ] | Admin | Survoler un point de la courbe | Bulle avec Recettes, Dépenses et **Solde** |
| [ ] | Admin | « Voir les chiffres » | Tableau jour par jour (ou mois par mois sur 12 mois) |
| [ ] | Admin | Bloc « Caisse du jour » | Montant du jour, anneau « % du prévu » s'il y a des rendez-vous aujourd'hui, barres des 7 derniers jours (survol = détail) |
| [ ] | Admin | Bloc « Stock bas » | Les 3 produits en alerte, avec une jauge. Cire épilatoire = « Rupture » |
| [ ] | Admin | Bloc « Heures de pointe » | Grille jour × heure ; survoler une case affiche le nombre de rendez-vous |
| [ ] | Réception | Ouvrir | Pas de cartes financières ni de graphique ; rendez-vous, caisse du jour, anniversaires visibles |
| [ ] | Pro | Ouvrir | « Mes rendez-vous d'aujourd'hui » seulement ; pas de caisse, pas de grille |
| [ ] | Admin | Un jour où un rendez-vous du jour est « Terminé » et pas payé | Bouton **« Encaisser X FCFA »** sur sa ligne, qui ouvre la caisse avec ce rendez-vous |

## 3. Recherche rapide (barre du haut)

| ✓ | Action | Résultat attendu |
|---|---|---|
| [ ] | Sur n'importe quelle page, appuyer sur la touche **/** | Le curseur va dans la barre de recherche |
| [ ] | Taper `awa` | « Awa [Démo] Diallo » apparaît |
| [ ] | Taper `77 000 01 02` | « Mariama [Démo] Sow » apparaît |
| [ ] | Taper `rokhaya` | Résultat avec le badge **Archivée** |
| [ ] | Flèches ↓ ↑ puis Entrée | Ouvre la fiche de la cliente choisie |
| [ ] | Se connecter en professionnel | Pas de barre de recherche |

## 4. Clients — `/clients`

| ✓ | Action | Résultat attendu |
|---|---|---|
| [ ] | Ouvrir la liste | 7 clientes actives ; Rokhaya (archivée) n'apparaît pas par défaut |
| [ ] | Filtre Statut = Archivé | Rokhaya apparaît |
| [ ] | Rechercher `diallo` puis `0102` | Trouve par nom, puis par numéro |
| [ ] | « Nouveau client » : Prénom `Test`, Nom `Guide`, Téléphone `77 123 45 67` | Fiche créée, numéro affiché au format +221 77 123 45 67 |
| [ ] | Recréer un client avec `+221771234567` | Refusé : numéro déjà enregistré |
| [ ] | Téléphone `123` | Refusé : numéro invalide |
| [ ] | Cocher « Consentement donné » puis enregistrer | La fiche affiche la date du consentement |
| [ ] | Fiche Rokhaya (admin) → réactiver | Elle redevient active |
| [ ] | Archiver « Test Guide » (admin) | Elle disparaît de la liste par défaut |
| [ ] | « Exporter en CSV » (admin) | Un fichier .csv se télécharge et s'ouvre dans Excel |
| [ ] | En réceptionniste | Peut créer et modifier, mais **pas** archiver ni exporter |

## 5. Prestations et forfaits — `/prestations`, `/forfaits`

| ✓ | Action | Résultat attendu |
|---|---|---|
| [ ] | Ouvrir /prestations | Les 8 prestations [Démo] avec prix et durée |
| [ ] | « Nouvelle prestation » : `Brushing test`, Coiffure femmes, 4 000 FCFA, 30 min | Créée |
| [ ] | Créer la même dans la même catégorie | Refusé : nom déjà utilisé |
| [ ] | Changer son prix à 4 500 | Enregistré |
| [ ] | La désactiver puis la réactiver | Le statut change ; désactivée, elle n'est plus proposée pour un rendez-vous |
| [ ] | /forfaits | Les 2 forfaits avec prix séparé et **économie** affichés |
| [ ] | Nouveau forfait avec une seule prestation | Refusé : il en faut au moins 2 différentes |
| [ ] | En réceptionniste ou pro | Consultation seulement, pas de bouton de création |

## 6. Équipe — `/equipe`

| ✓ | Action | Résultat attendu |
|---|---|---|
| [ ] | Ouvrir | Les 3 employés [Démo] |
| [ ] | Fiche Awa Fall | Horaires lundi–samedi, dimanche en repos, commission 20 % |
| [ ] | « Horaires de travail » : ajouter une plage avec fin avant le début | Refusé |
| [ ] | Deux plages qui se chevauchent le même jour | Refusé |
| [ ] | « Ajouter lundi à vendredi (09:00–18:00) » puis enregistrer | Horaires remplacés |
| [ ] | Nouvel employé, compte de connexion = un compte déjà lié | Refusé : un compte = un seul employé |
| [ ] | En réceptionniste | Consulte l'équipe, ne voit pas les commissions, ne modifie rien |

## 7. Rendez-vous — `/rendez-vous`

| ✓ | Action | Résultat attendu |
|---|---|---|
| [ ] | Ouvrir l'agenda | Vues Jour / Semaine / Mois ; les rendez-vous [Démo] sont visibles |
| [ ] | « Nouveau rendez-vous » | Formulaire Client / Prestations / Personne / Créneau |
| [ ] | Rechercher le client `awa` | La liste apparaît ; cliquer pour choisir |
| [ ] | Cocher Coupe femme, choisir Awa Fall, une date en semaine | Les **créneaux libres** s'affichent, le résumé indique 45 min et 5 000 FCFA |
| [ ] | Choisir un créneau → Enregistrer | Fiche du rendez-vous créée, statut Confirmé |
| [ ] | Même personne, même heure | Le créneau n'est plus proposé (pas de double réservation) |
| [ ] | Choisir un dimanche | Aucun créneau (jour sans horaires) |
| [ ] | Tresses simples avec Mariama Diop | Impossible : elle ne propose pas cette prestation |
| [ ] | Forfait Mains et pieds avec Mariama | Prix 10 000 FCFA, durée 1 h 45 |
| [ ] | Sur la fiche : « Modifier ou déplacer » vers un autre créneau | Déplacé ; l'historique des modifications le note |
| [ ] | Statuts : Client arrivé → En cours → Terminé | Seuls les boutons possibles s'affichent à chaque étape |
| [ ] | Sur un rendez-vous futur | Pas de bouton « Client arrivé » (seulement annuler) |
| [ ] | Annuler un rendez-vous avec un motif | Statut Annulé, motif gardé, créneau libéré |
| [ ] | En **professionnel** | Ne voit que les rendez-vous d'Awa Fall ; peut passer Arrivé / En cours / Terminé ; ne peut ni créer, ni déplacer, ni annuler |

## 8. Caisse — `/caisse`

| ✓ | Action | Résultat attendu |
|---|---|---|
| [ ] | Ouvrir | Liste des paiements ; l'admin voit les totaux par mode |
| [ ] | Sur le rendez-vous d'Aminata (manucure, payée à moitié) → Encaisser | Le formulaire indique **reste 2 500 FCFA** |
| [ ] | Saisir 3 000 | Refusé : plus que le reste à payer |
| [ ] | « Solder (payer tout le reste) », mode Orange Money | Payé ; le rendez-vous n'est plus dans les impayés |
| [ ] | « Voir le reçu » → Imprimer | Reçu propre, imprimable |
| [ ] | « Vente sans rendez-vous » : cliente Awa Diallo, libellé `Shampoing`, 7 500 FCFA | Enregistrée |
| [ ] | Vente sans client ni libellé | Refusée |
| [ ] | Admin : « Corriger ce paiement » (nouveau montant + motif) | Corrigé, l'ancienne ligne est annulée et remplacée |
| [ ] | Admin : « Annuler ce paiement » avec motif | Une ligne négative apparaît ; plus aucune action possible sur ce paiement |
| [ ] | /caisse/impayes | Liste des rendez-vous terminés pas entièrement payés, avec total |
| [ ] | En réceptionniste | Peut encaisser, **ne peut pas** corriger ni annuler, ne voit pas les totaux |
| [ ] | En professionnel | Pas d'accès à la caisse |

## 9. Dépenses — `/depenses` (admin)

| ✓ | Action | Résultat attendu |
|---|---|---|
| [ ] | Ouvrir | 4 dépenses [Démo], totaux du jour et du mois, répartition par catégorie |
| [ ] | « Nouvelle dépense » : Loyer, 150 000, aujourd'hui, avec une photo de justificatif | Enregistrée ; le justificatif s'ouvre depuis la fiche |
| [ ] | Date dans le futur | Refusé |
| [ ] | Justificatif `.exe` ou trop lourd | Refusé |
| [ ] | Modifier le montant | Enregistré ; le justificatif ne peut pas être remplacé |
| [ ] | Annuler la dépense avec motif | Barrée dans la liste ; plus modifiable |

## 10. Stock — `/stock`

| ✓ | Action | Résultat attendu |
|---|---|---|
| [ ] | Ouvrir | 8 produits ; filtre « Stock bas seulement » → 3 produits |
| [ ] | « Nouveau produit » `Gel test`, stock initial 5 | Créé avec un mouvement d'entrée |
| [ ] | Nouveau mouvement « Entrée » 10 sur la Cire épilatoire | Quantité passe à 10, elle sort du stock bas |
| [ ] | Sortie de plus que le stock | Refusé |
| [ ] | Perte ou inventaire sans motif | Refusé : motif obligatoire |
| [ ] | Inventaire : quantité comptée 7 | Le stock est corrigé à 7 |
| [ ] | Modifier la fiche produit | La quantité ne change pas (seulement via les mouvements) |

## 11. Fidélité — `/fidelite`

| ✓ | Action | Résultat attendu |
|---|---|---|
| [ ] | Ouvrir, chercher Cheikh | 2 points (2 500 FCFA payés) |
| [ ] | Encaisser 5 000 FCFA pour une cliente | Elle gagne **5 points** |
| [ ] | Annuler ce paiement | Les 5 points sont retirés |
| [ ] | « Offrir une récompense » sans assez de points | Refusé : points insuffisants |
| [ ] | Admin : « Corriger les points » +30 avec motif | Solde mis à jour, ligne dans l'historique |
| [ ] | /fidelite/recompenses : nouvelle récompense | Créée, peut être désactivée |
| [ ] | En réceptionniste | Peut offrir une récompense, ne peut pas corriger les points |

## 12. Anniversaires — `/anniversaires`

| ✓ | Action | Résultat attendu |
|---|---|---|
| [ ] | Ouvrir | Clientes triées par date d'anniversaire la plus proche, avec l'âge |
| [ ] | Section des dates manquantes | Khady (pas de date de naissance) y figure |
| [ ] | « Souhaiter sur WhatsApp » sur une cliente consentante | WhatsApp s'ouvre avec le message pré-rempli ; la ligne est marquée comme souhaitée |
| [ ] | Cliente sans consentement (Fatou) | Pas de bouton d'envoi |

## 13. Campagnes WhatsApp — `/campagnes` (admin)

| ✓ | Action | Résultat attendu |
|---|---|---|
| [ ] | « Nouvelle campagne », cible « Toutes » | L'aperçu compte seulement les clientes **actives et consentantes** (Awa, Mariama, Khady, Cheikh) |
| [ ] | Changer de cible (absentes / nouvelles inscrites) | Le nombre se met à jour en direct |
| [ ] | « Enregistrer le brouillon » puis « Modifier » | Le brouillon se modifie |
| [ ] | « Lancer la campagne » | La liste des destinataires est figée |
| [ ] | « Envoyer » sur une ligne | WhatsApp s'ouvre avec le message ; la ligne est marquée envoyée |
| [ ] | « Clôturer la campagne » | Plus aucun envoi possible |
| [ ] | En réceptionniste | Pas d'accès |

## 14. Rapports — `/rapports` (admin)

| ✓ | Action | Résultat attendu |
|---|---|---|
| [ ] | Ouvrir (Ce mois) | Encaissé, Dépenses, Résultat, Panier moyen, comparaison avec le mois dernier |
| [ ] | Cliquer Mois dernier / 30 derniers jours / Cette année / Dates au choix | Les chiffres changent |
| [ ] | Blocs mode de paiement, catégories, prestations, statuts, équipe | Remplis et cohérents avec la caisse |
| [ ] | « Exporter (Excel / CSV) » | Fichier téléchargé, lisible dans Excel |

## 15. Utilisateurs — `/utilisateurs` (admin)

| ✓ | Action | Résultat attendu |
|---|---|---|
| [ ] | Ouvrir | Les 3 comptes (admin, réception, Awa Fall) |
| [ ] | « Nouveau compte » rôle Réceptionniste | Créé ; la personne peut se connecter |
| [ ] | E-mail déjà utilisé | Refusé |
| [ ] | Ajouter un droit individuel (ex. « Exporter les clients ») | Ce compte voit le bouton Exporter sur /clients |
| [ ] | Désactiver un compte connecté ailleurs | Il est déconnecté et ne peut plus se reconnecter |
| [ ] | Essayer de désactiver son propre compte ou changer son propre rôle | Refusé |

## 16. Paramètres — `/parametres` (admin)

| ✓ | Action | Résultat attendu |
|---|---|---|
| [ ] | Changer le nom du salon | Le nouveau nom apparaît sur la page de connexion et les reçus |
| [ ] | Remplir adresse et téléphone | Ils apparaissent sur les reçus |
| [ ] | Pause entre rendez-vous = 15 min | Les créneaux proposés laissent 15 min entre deux rendez-vous d'une même personne |
| [ ] | Fidélité = 0 | Le programme est en pause : un encaissement ne rapporte plus de points |
| [ ] | Modifier le message d'anniversaire | Le nouveau texte est utilisé dans WhatsApp |
| [ ] | En réceptionniste | Pas d'accès (403) |

## 17. Téléphone et affichage

À faire avec les outils du navigateur (**F12** → icône téléphone) ou sur un vrai téléphone.

| ✓ | Action | Résultat attendu |
|---|---|---|
| [ ] | Largeur 390 px, tableau de bord | Aucun défilement horizontal ; tout se lit en une colonne |
| [ ] | Bouton menu ☰ | Le menu s'ouvre et se ferme |
| [ ] | Formulaire de rendez-vous sur téléphone | Utilisable : recherche client, prestations, créneaux |
| [ ] | Console (F12) sur chaque page | **Aucune erreur rouge** (en particulier pas d'erreur « CORS ») |

---

## Signaler un problème

Pour chaque test en échec, notez :

1. le **compte** utilisé (admin / réception / pro) ;
2. la **page** (adresse complète) ;
3. ce que vous avez **fait** ;
4. ce qui s'est **passé** à la place du résultat attendu ;
5. une capture d'écran et, s'il y en a, l'erreur rouge de la console (F12).
