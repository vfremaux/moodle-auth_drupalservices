<?php
/**
 * Authentication Plugin: Drupal Services Single Sign-on
 *
 * This module is based on work by Arsham Skrenes.
 * This module will look for a Drupal cookie that represents a valid,
 * authenticated session, and will use it to create an authenticated Moodle
 * session for the same user. The Drupal user will be synchronized with the
 * corresponding user in Moodle. If the user does not yet exist in Moodle, it
 * will be created.
 *
 * PHP version 5
 *
 * @category CategoryName
 * @package  Drupal_Services
 * @author   Dave Cannon <dave@baljarra.com>
 * @license  http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @link     https://github.com/cannod/moodle-drupalservices
 *
 */

$string['drupalservices:config'] = 'Peut configurer le SSO Drupal';

$string['pluginname'] = 'Services Drupal';

$string['servicestatus_header']= 'Etat des services Drupal Service';
$string['servicestatus_header_info']= 'L\'état de la liaison SSO Moodle/Drupal est indiqué ci-dessous';


$string['servicesettings_header'] = 'Réglages des services Drupal';

$string['servicesettings_header_info'] = 'Les réglages suivants permettent de configurer la liaison SSO entre Moodle et un site Drupal.';

$string['servicesettings_header_info_firsttime'] = 'Il semble que ce soit la première fois que cette configuration soit faite. Moodle a tenté de découvrir automatiquement
la configuration correcte du SSO. Vérifiez que l\'URL du site Drupal est correcte.';

$string['userfieldmap_header'] = 'Correspondance des attributs de profil';
$string['userfieldmap_header_desc'] = 'Cette correspondance met en relation les attributs de profil utilisateur de Moodle avec les données correspondantes dans le site Drupal. Les données de profil Moodle seront mises à jour chaque fois que les utilisateur Drupal se connecteront dans Moodle. Si l\'import massif de compte est activé, les profils Moodle seront alors aussi resynchronisés à chaque passage de l\'import.';
$string['fieldmap'] = 'Champ drupal pour {$a}';

$string['userimport_header'] = 'Réglage de l\'import/migration d\'utilisateurs Drupal';
$string['userimport_header_desc'] = 'Ces réglages permettent de configurer un import massif et une synchronisation régulière des utilisateurs Drupal dans Moodle à partir du script sync_users.php. Seuls les utilisateurs présents dans Drupal associés au rôle "Moodle Services" seront importés. Les identifiants de compte ci-dessous doivent être renseignés. Tous les attributs dont les correspondances sont définies ci-avant seront importés.';

$string['auth_drupalservicesdescription'] = 'Ce plugin d\'authentification permet de mettre en place un Single Sign-on (SSO) avec Drupal. Ce module examine dans le contexte local de l\'utilisateur l\'existence d\'un cookie Drupal conforme à l\'identité du site lié et indiquant la présence d\'une session Drupal active. cette information est réutilisée pour créer automatiquement une session Moodle poiur le compte utilisateur Moodle qui correspond. Le profil de l\'utilisateur Moodle activé par la session sera resynchronisé (pour les attributs en correspondance) avec les données courantes du compte Drupal. Si l\'utilisateur n\'est pas encore référencé dans Moodle, alors il sera créé. Le plugin de services côté Drupal doit être installé pour que la liaison fonctionne. Lisez attentivement le fichier README pour les instructions \'installation.';

$string['auth_drupalservices_debug_key'] = 'Deboggage';
$string['auth_drupalservices_debug'] = 'Si actif, sortira tous les messages de deboggage.';
$string['auth_drupalservices_autodetect_key'] = 'Autodetection Drupal';
$string['auth_drupalservices_autodetect'] = 'Si actif, Moodle essayera d\'auto détecter la configuration de drupal, sur la base de l\'installation recommandée.';
$string['auth_drupalservices_duallogin_key'] = 'Double authentification';
$string['auth_drupalservices_duallogin'] = 'Si activé, les utilisateurs devront choisir si leur compte est un compte local à Moodle ou un compte distant de Drupal.';
$string['auth_drupalservices_host_uri_key'] = 'URL du site Drupal';
$string['auth_drupalservices_host_uri'] = 'Hôte et chemin d\'accès au site Drupal lié par SSO. Doit inclure le préfixe de protocole (http:// ou https://) et ne doit pas comporter de slash \'/\' final.';
$string['auth_drupalservices_remote_user_key'] = 'Identifiant administrateur distant';
$string['auth_drupalservices_remote_user'] = 'Il s\'agit de l\'identifiant du compte Drupal qui a le droit de voir les profils de tous les utilisateurs. Cet utilisateur doit avoir tous les accès. Voir la doc Drupal pour la configuration de ce compte.';
$string['auth_drupalservices_remote_pw_key'] = 'Mot de passe administrateur distant';
$string['auth_drupalservices_remote_pw'] = 'Le mot de passe du compte administrateur Drupal.';
$string['auth_drupalservicesremove_user_key'] = 'Utilisateur distant supprimé';
$string['auth_drupalservicesremove_user'] = 'Indiquer le traitement fait par la synchronisation du compte Moodle local, si le compte associé sur Drupal a été supprimé. Rétablir un utilisateur qui serait rétabli dans Drupal n\'est possible que si un utilisateur manquant est "suspendu".';
$string['auth_drupalservices_cohorts_key'] = 'Créer les cohortes';
$string['auth_drupalservices_cohorts'] = 'Permet de créer automatiquement des cohortes en invoquant une vue particulière sur Drupal.';
$string['auth_drupalservices_cohort_view_key'] = 'chemin vers la vue de création de cohortes';
$string['auth_drupalservices_cohort_view'] = 'Indiquez le chemin vers la vue Drupal de création des cohortes.';

$string['auth_drupalservicesnorecords'] = 'Drupal n\'a aucun utilisateur référencé pour l\'import !';
$string['auth_drupalservicescreateaccount'] = 'Impossible de créer le compte utilisateur Moodle pour {$a}';
$string['auth_drupalservicesdeleteuser'] = 'Utilisateur {$a->name} d\'id {$a->id} supprimé';
$string['auth_drupalservicesdeleteusererror'] = 'Impossible de supprimer l\'utilisateur {$a}';
$string['auth_drupalservicessuspenduser'] = 'Utilisateur {$a->name} d\'id {$a->id} suspendu';
$string['auth_drupalservicessuspendusererror'] = 'Impossible de suspendre l\'utilisateur {$a}';
$string['auth_drupalservicesuserstoremove'] = 'Utilisateurs à supprimer : {$a}';
$string['auth_drupalservicescantinsert'] = 'Erreur DB. Impossible d\'insérer l\'utiilisateur : {$a}';
$string['auth_drupalservicescantupdate'] = 'Erreur DB. Impossible de mettre à jour l\'utilisateur : {$a}';
$string['auth_drupalservicesuserstoupdate'] = 'Utilisateurs à mettre à jour : {$a}';
$string['auth_drupalservicesupdateuser'] = 'Utilisateur {$a} modifié';

$string['auth_drupalservices_logout_drupal_key'] = 'Se déconnecter de Drupal lors de la déconnexion de Moodle';
$string['auth_drupalservices_logout_drupal'] = 'Cette case devrait rester cochée dans la plupart des cas. Si votre site Drupal exploite la "connexion pour le compte d\'un autre utilisateur", il est possible que vous souhaitiez désactiver cette fonction pour faciliter le basculement entre comptes.';

$string['erroruserexistsinternally'] = 'Votre compte utilisateur n\'a pas pu être créé sur ce Moodle car un utilisateur de même nom existe déjà avec une méthode d\'authentification différente. Contactez les administrateurs.';

$string['enableauth'] = 'Activation des services Drupal';
$string['enabling_info'] = 'La configuration complète de ce plugin suppose une activation préalable de cette méthode d\'authentification';
$string['debug'] = 'Message de deboggage ';
$string['misconfig'] = 'Erreur de configuration ';
$string['errormessage'] = 'Erreur SSO Drupal ';
$string['drupalaccounts'] = 'Se connecter avec un compte Drupal';
$string['moodleaccounts'] = 'Se connecter avec un compte local';
$string['drupalduallogin'] = 'Double authentification Drupal';
$string['drupalmanualsync'] = 'Synchronisation manuelle';
$string['rundrupalmanualsync'] = 'Synchroniser drupal en mode interactif';
$string['confirmrunsync'] = 'Confirmez la synchronisation';
$string['confirmrunsyncall'] = 'Confirmez pour une synchronisation complete';
