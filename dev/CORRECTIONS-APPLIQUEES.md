# Corrections Appliquées - CSWeb Multi-Tenant

## 🔧 Problème Résolu : Erreur SuperAdminController

### Erreur Initiale
```
Fatal error: Uncaught Error: Class "Symfony\Bundle\FrameworkBundle\Controller\Controller" not found
while loading "AppBundle\Controller\ui\SuperAdminController"
```

### Cause
Le contrôleur `SuperAdminController` utilisait l'ancienne classe de base `Controller` qui n'existe plus dans Symfony 5.x. La classe a été remplacée par `AbstractController`.

### Solution Appliquée

#### 1. Mise à jour de la classe de base

**Avant** :
```php
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class SuperAdminController extends Controller
{
    public function dashboardAction()
    {
        $em = $this->getDoctrine()->getManager();
        // ...
    }
}
```

**Après** :
```php
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface;

class SuperAdminController extends AbstractController
{
    public function dashboardAction(EntityManagerInterface $em)
    {
        // Injection de dépendance au lieu de $this->getDoctrine()
        // ...
    }
}
```

#### 2. Modifications apportées dans SuperAdminController.php

- ✅ Changement de `Controller` → `AbstractController`
- ✅ Ajout de `use Doctrine\ORM\EntityManagerInterface`
- ✅ Injection de `EntityManagerInterface $em` dans chaque méthode
- ✅ Suppression de `$this->getDoctrine()->getManager()` (remplacé par injection de dépendance)

**Méthodes mises à jour** :
1. `dashboardAction(EntityManagerInterface $em)`
2. `organizationsAction(EntityManagerInterface $em)`
3. `createOrganizationAction(Request $request, EntityManagerInterface $em)`
4. `editOrganizationAction(Request $request, $id, EntityManagerInterface $em)`
5. `organizationConnectionsAction($id, EntityManagerInterface $em)`
6. `createConnectionAction(Request $request, $orgId, EntityManagerInterface $em, DatabaseConnectionManager $connectionManager)`
7. `testConnectionAction($id, EntityManagerInterface $em, DatabaseConnectionManager $connectionManager)`
8. `toggleConnectionAction($id, EntityManagerInterface $em)`
9. `deleteConnectionAction($id, EntityManagerInterface $em)`

#### 3. Nettoyage du cache et autoload

```bash
docker exec php bash -c "cd /var/www/html && rm -rf var/cache/*"
docker exec php bash -c "cd /var/www/html && composer dump-autoload --optimize"
```

---

## 📦 Problème Résolu : Dépendances Composer Manquantes

### Erreur Initiale
```
Warning: require(/var/www/html/vendor/autoload.php): Failed to open stream: No such file or directory
Fatal error: Failed opening required '/var/www/html/vendor/autoload.php'
```

### Cause
Les dépendances Composer n'étaient pas installées dans le conteneur PHP après le premier démarrage.

### Solution Appliquée

#### 1. Installation manuelle de Composer

```bash
docker exec php bash -c "curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer"
```

#### 2. Installation des dépendances

```bash
docker exec php composer install --working-dir=/var/www/html --no-interaction --optimize-autoloader
```

#### 3. Correction des permissions

```bash
docker exec php chown -R www-data:www-data /var/www/html/app /var/www/html/src /var/www/html/var /var/www/html/files /var/www/html/web
```

#### 4. Script d'automatisation créé

**Fichier** : [dev/setup-csweb.sh](setup-csweb.sh)

Ce script automatise :
- ✅ Vérification de Docker et Docker Compose
- ✅ Démarrage des conteneurs
- ✅ Attente du démarrage de MySQL
- ✅ Installation de Composer
- ✅ Installation des dépendances PHP
- ✅ Correction des permissions
- ✅ Nettoyage du cache

**Usage** :
```bash
cd dev
./setup-csweb.sh
```

---

## 📝 Documentation Mise à Jour

### Fichiers modifiés

1. **[README.md](../README.md)**
   - Ajout de l'étape Composer dans Quick Start
   - Référence au script `setup-csweb.sh`
   - Mise à jour des noms de conteneurs (`php` au lieu de `csweb_php`)

2. **[dev/SETUP-STATUS.md](SETUP-STATUS.md)**
   - Documentation de l'état actuel de l'installation
   - Liste complète des étapes complétées
   - Ajout des corrections SuperAdminController et Composer

3. **Nouveau : [dev/setup-csweb.sh](setup-csweb.sh)**
   - Script bash pour automatiser l'installation
   - Gestion des erreurs et affichage coloré
   - Instructions post-installation

---

## ✅ Résultat

### Avant les corrections
- ❌ Erreur fatale au chargement de la page
- ❌ SuperAdminController non chargé
- ❌ Dépendances Composer manquantes

### Après les corrections
- ✅ Interface CSWeb s'affiche correctement
- ✅ SuperAdminController compatible Symfony 5
- ✅ Toutes les dépendances installées
- ✅ Autoload fonctionnel
- ✅ Cache nettoyé
- ✅ Permissions corrigées

### Prochaines étapes

1. **Configuration CSWeb via navigateur** (`http://localhost`)
   - Database host: `mysql`
   - Database name: `cspro`
   - Database user: `cspro`
   - Database password: `cspro`

2. **Migration multi-tenant**
   ```bash
   docker exec php php bin/console doctrine:migrations:migrate --no-interaction
   ```

3. **Création d'une organisation**
   ```bash
   docker exec php php bin/console csweb:org:create --code=ANSD --name="ANSD Sénégal"
   ```

4. **Accès à l'interface multi-tenant**
   - URL: `http://localhost/admin/multi-tenant`

---

## 🛠️ Bonnes Pratiques Appliquées

1. **Injection de dépendance** : Utilisation d'`EntityManagerInterface` au lieu de `getDoctrine()`
2. **Compatibilité Symfony 5** : Migration vers `AbstractController`
3. **Automatisation** : Script bash pour faciliter le déploiement
4. **Documentation** : Mise à jour complète de la documentation
5. **Permissions** : Correction des permissions pour www-data
6. **Cache** : Nettoyage et optimisation de l'autoload

---

---

## 🆕 Correction 3 : Entité Dictionary Manquante

### Erreur
```
Doctrine\ORM\Mapping\MappingException: The target-entity AppBundle\Entity\Dictionary cannot be found in 'AppBundle\Entity\Organization#dictionaries'
```

### Cause
L'entité `Organization` référençait `Dictionary` dans sa relation `OneToMany`, mais l'entité Dictionary n'existait pas encore.

### Solution

#### 1. Création de l'entité Dictionary

**Fichier** : `src/AppBundle/Entity/Dictionary.php`

Propriétés :
- `id` : Identifiant unique
- `dictionaryName` : Nom du dictionnaire (unique)
- `description` : Description optionnelle
- `organization` : Relation ManyToOne vers Organization
- `dbConnection` : Relation ManyToOne vers DatabaseConnection (optionnelle)
- `createdAt` / `updatedAt` : Timestamps
- `isActive` : Statut actif/inactif

#### 2. Création du Repository

**Fichier** : `src/AppBundle/Repository/DictionaryRepository.php`

Méthodes :
- `findByOrganization()` : Trouve tous les dictionnaires actifs d'une organisation
- `findOneByNameAndOrganization()` : Trouve un dictionnaire par nom et organisation

#### 3. Nettoyage du cache

```bash
docker exec php bash -c "cd /var/www/html && rm -rf var/cache/* && composer dump-autoload --optimize"
```

### ✅ Résultat

- ✅ Entité Dictionary créée et configurée
- ✅ Relations Doctrine complètes
- ✅ Repository fonctionnel
- ✅ Application se charge sans erreur

---

## 🆕 Correction 4 : Erreur API lors du Login

### Erreur
```
JsonException: "Syntax error" at LoginController.php line 47
```
L'API retournait du HTML (erreur fatale) au lieu de JSON lors de l'authentification.

### Cause Racine
1. Les commandes CLI multi-tenant (`Command/`) étaient chargées par le kernel API
2. Ces commandes nécessitent `EntityManagerInterface` (Doctrine)
3. Doctrine n'est configuré que pour le kernel UI, pas pour le kernel API
4. L'API crashait avec une erreur fatale au lieu de retourner du JSON

### Solution

#### 1. Exclusion des services multi-tenant du kernel API

**Fichier** : `app/config/api/services.yml`

```yaml
AppBundle\:
    resource: '../../../src/AppBundle/*'
    exclude: '../../../src/AppBundle/{Entity,Repository,Tests,Command,version.php,config.php,Controller/ui,Service/HttpHelper.php,Service/DatabaseConnectionManager.php}'
```

Ajout de :
- `Command` : Commandes CLI multi-tenant (nécessitent Doctrine)
- `Service/DatabaseConnectionManager.php` : Service multi-tenant (nécessite Doctrine)

#### 2. Synchronisation des utilisateurs OAuth

**Problème** : Les utilisateurs étaient dans `cspro_users` mais pas dans `oauth_users`

**Solution** :
```sql
INSERT INTO oauth_users (username, password, first_name, last_name)
SELECT username, password, 'Admin', 'User'
FROM cspro_users WHERE username='admin';
```

#### 3. Réinitialisation du mot de passe admin

**Commandes** :
```bash
# Générer le hash bcrypt pour 'admin'
docker exec php php -r "echo password_hash('admin', PASSWORD_BCRYPT);"

# Mettre à jour le mot de passe
docker exec mysql mysql -uroot -prootpassword cspro -e "UPDATE oauth_users SET password='$HASH' WHERE username='admin';"
docker exec mysql mysql -uroot -prootpassword cspro -e "UPDATE cspro_users SET password='$HASH' WHERE username='admin';"
```

### ✅ Résultat

**Avant** :
```bash
curl http://localhost/api/token
# Retournait: Fatal error: Cannot autowire EntityManagerInterface...
```

**Après** :
```bash
curl -X POST http://localhost/api/token \
  -H "Content-Type: application/json" \
  -d '{"client_id":"cspro_android","client_secret":"cspro","grant_type":"password","username":"admin","password":"admin"}'

# Retourne:
{
  "access_token": "c55d62addf8efc50998860b31c3dda94ff99ff1b",
  "expires_in": 3600,
  "token_type": "Bearer",
  "scope": null,
  "refresh_token": "0c8b06734dad3e671ba4f6772e468a735c8065cf"
}
```

### Credentials de connexion

**URL** : `http://localhost`
- **Username** : `admin`
- **Password** : `admin`

---

**Date des corrections** : 27 novembre 2024
**Version** : CSWeb Multi-Tenant 1.0.0-beta
**Status** : ✅ Toutes les erreurs résolues - Application fonctionnelle - Login opérationnel
