# Architecture Globale - CSWeb Multi-Tenant

## Principe Fondamental

### Base de Données Principale (MySQL) - IMPOSÉE PAR CSPRO

**MySQL** est la base de données **principale et obligatoire** de CSWeb. Elle stocke :

- ✅ **Métadonnées** : Dictionnaires, configurations, utilisateurs, organisations
- ✅ **Données BLOB** : Questionnaires synchronisés depuis les mobiles (format binaire compressé)
- ✅ **Historique de synchronisation** : cspro_sync_history
- ✅ **OAuth tokens** : Authentification API

⚠️ **Cette base MySQL ne peut PAS être changée** - c'est une exigence de CSPro/CSWeb.

### Bases de Données Destinataires (Multi-SGBD) - CHOIX LIBRE

Le **breakout** transforme les données BLOB de MySQL vers des **tables tabulaires** dans le SGBD de votre choix :

- ✅ **PostgreSQL** (recommandé pour gros volumes)
- ✅ **MySQL** (même serveur ou serveur différent)
- ✅ **SQL Server** (pour environnements Microsoft/gouvernements)

**Chaque organisation choisit** :

- Le type de SGBD
- Le serveur (interne ou externe)
- La base de données destinataire
- Les credentials

## Flux de Données

```
┌─────────────────────────────────────────────────────────────────────┐
│                     SYNCHRONISATION MOBILE                          │
└─────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────┐
│            MySQL Principal (CSWeb - IMPOSÉ)                         │
│  ┌──────────────────────────────────────────────────────────┐      │
│  │ Table: DICT_RECENSEMENT                                   │      │
│  │ ├── guid (binary)                                         │      │
│  │ ├── caseids (varchar)                                     │      │
│  │ ├── questionnaire (BLOB COMPRESSÉ) ◄─── Données mobiles  │      │
│  │ ├── revision (int)                                        │      │
│  │ └── modified_time (timestamp)                             │      │
│  └──────────────────────────────────────────────────────────┘      │
└─────────────────────────────────────────────────────────────────────┘
                                    │
                         ┌──────────┴──────────┐
                         │   BREAKOUT PROCESS  │ ◄── Transformation BLOB → Tables
                         └──────────┬──────────┘
                                    │
        ┌───────────────────────────┼───────────────────────────┐
        │                           │                           │
        ▼                           ▼                           ▼
┌────────────────┐      ┌────────────────┐      ┌────────────────┐
│  PostgreSQL    │      │     MySQL      │      │  SQL Server    │
│  (ANSD)        │      │  (Cabinet)     │      │  (Ministère)   │
├────────────────┤      ├────────────────┤      ├────────────────┤
│ Table: MENAGES │      │ Table: MENAGES │      │ Table: MENAGES │
│ ├── id         │      │ ├── id         │      │ ├── id         │
│ ├── region     │      │ ├── region     │      │ ├── region     │
│ ├── district   │      │ ├── district   │      │ ├── district   │
│ ├── nom_cm     │      │ ├── nom_cm     │      │ ├── nom_cm     │
│ └── ...        │      │ └── ...        │      │ └── ...        │
│                │      │                │      │                │
│ DONNÉES        │      │ DONNÉES        │      │ DONNÉES        │
│ TABULAIRES     │      │ TABULAIRES     │      │ TABULAIRES     │
│ ✓ Exploitables │      │ ✓ Exploitables │      │ ✓ Exploitables │
└────────────────┘      └────────────────┘      └────────────────┘
```

## Configuration Multi-Tenant

### Architecture de Base de Données

```sql
-- ============================================
-- MySQL Principal (CSWeb) - NE CHANGE PAS
-- ============================================
Database: cspro
Tables:
  - cspro_dictionaries             -- Dictionnaires CSPro
  - cspro_organizations            -- Organisations (NOUVEAU)
  - cspro_organization_db_connections  -- Connexions multi-SGBD (NOUVEAU)
  - cspro_sync_history             -- Historique synchro
  - DICT_NAME (dynamiques)         -- Tables de données BLOB

-- ============================================
-- Bases Destinataires (Multi-SGBD) - CHOIX LIBRE
-- ============================================

Organisation ANSD -> PostgreSQL
Database: ansd_data
Tables: MENAGES, INDIVIDUS, DECES, etc. (tabulaires)

Organisation INS_BENIN -> MySQL
Database: ins_benin_data
Tables: MENAGES, INDIVIDUS, etc. (tabulaires)

Organisation MINISTERE -> SQL Server
Database: ministere_dw
Tables: MENAGES, INDIVIDUS, etc. (tabulaires)
```

## Pourquoi Cette Architecture ?

### Raisons de Sécurité (CSWEB/CSPRO)

1. **Isolation des données brutes** : Les BLOBs restent dans MySQL isolé
2. **Audit trail complet** : Toutes les synchronisations tracées dans MySQL
3. **Intégrité garantie** : Format binaire CSPro protégé
4. **Rollback possible** : Toujours possibilité de re-breakout depuis les BLOBs

### Avantages du Breakout Multi-SGBD

1. **Performance** : PostgreSQL pour gros volumes > MySQL
2. **Standardisation** : Beaucoup de gouvernements imposent SQL Server
3. **Intégration** : Connexion directe à un datawarehouse existant
4. **Flexibilité** : Chaque organisation utilise son infrastructure existante

## Exemple Concret : ANSD Sénégal

### Configuration

```yaml
Organisation: ANSD
Code: ANSD
Type: statistics_office

Connexions DB:
  1. MySQL Principal (CSWeb)
     - Host: mysql
     - Database: cspro
     - Usage: Métadonnées + BLOBs
     - IMPOSÉ

  2. PostgreSQL (Breakout)
     - Host: postgres
     - Database: ansd_recensement
     - Usage: Données tabulaires recensement
     - CHOIX ANSD

  3. MySQL Externe (Breakout enquêtes)
     - Host: mysql-externe.ansd.sn
     - Database: ansd_enquetes
     - Usage: Petites enquêtes
     - CHOIX ANSD

  4. SQL Server (Datawarehouse)
     - Host: datawarehouse.ansd.sn
     - Database: ansd_dw
     - Usage: Entrepôt de données
     - CHOIX ANSD
```

### Flux pour le Recensement ANSD

1. **Synchronisation mobile → MySQL**

   ```
   Mobile CSEntry → API CSWeb → MySQL (cspro) → Table RECENSEMENT_2024
   Stockage : BLOB compressé dans colonne `questionnaire`
   ```

2. **Breakout → PostgreSQL**

   ```
   Processus breakout → Lecture BLOB MySQL → Transformation → Insertion PostgreSQL
   Database: ansd_recensement
   Tables: MENAGES, INDIVIDUS, BATIMENTS, etc.
   Format: Données tabulaires exploitables
   ```

3. **Exploitation**
   ```
   Analystes ANSD → Connexion PostgreSQL ansd_recensement
   Outils: SPSS, Stata, R, Python, Tableau
   SQL direct sur données tabulaires
   ```

## Drivers et Pilotes

### Dans Docker

Le conteneur PHP CSWeb doit avoir les extensions pour tous les SGBD :

```dockerfile
# PHP Extensions installées
- pdo_mysql      ✓ (obligatoire)
- pdo_pgsql      ✓ (pour PostgreSQL)
- sqlsrv         ✓ (pour SQL Server)
- pdo_sqlsrv     ✓ (pour SQL Server)

# Bibliothèques
- MySQL Client Libraries
- PostgreSQL Client Libraries
- Microsoft ODBC Driver for SQL Server
```

### Configuration php/Dockerfile

```dockerfile
FROM php:8.2-apache

# MySQL (obligatoire)
RUN docker-php-ext-install pdo_mysql mysqli

# PostgreSQL
RUN apt-get update && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo_pgsql pgsql

# SQL Server (optionnel mais recommandé)
RUN curl https://packages.microsoft.com/keys/microsoft.asc | apt-key add - \
    && curl https://packages.microsoft.com/config/debian/11/prod.list > /etc/apt/sources.list.d/mssql-release.list \
    && apt-get update \
    && ACCEPT_EULA=Y apt-get install -y msodbcsql18 \
    && pecl install sqlsrv pdo_sqlsrv \
    && docker-php-ext-enable sqlsrv pdo_sqlsrv
```

## Tables Concernées par le Breakout

### Dans MySQL (Source - BLOBs)

```sql
-- Table générée automatiquement par CSWeb pour chaque dictionnaire
CREATE TABLE DICT_RECENSEMENT (
    id INT UNSIGNED AUTO_INCREMENT,
    guid BINARY(16) NOT NULL,
    caseids VARCHAR(191) NOT NULL,
    label VARCHAR(255),
    questionnaire BLOB NOT NULL,    -- ◄── BLOB COMPRESSÉ (données mobiles)
    revision INT UNSIGNED NOT NULL,
    deleted TINYINT(1),
    verified TINYINT(1),
    clock TEXT,
    modified_time TIMESTAMP,
    PRIMARY KEY (guid)
);
```

### Dans PostgreSQL/MySQL/SQL Server (Destination - Tabulaires)

```sql
-- Tables créées par le processus de breakout
CREATE TABLE MENAGES (
    menage_id SERIAL PRIMARY KEY,
    region VARCHAR(50),
    district VARCHAR(50),
    commune VARCHAR(100),
    quartier VARCHAR(100),
    numero_menage INT,
    nom_chef_menage VARCHAR(255),
    taille_menage INT,
    type_habitat VARCHAR(50),
    -- ... autres colonnes du dictionnaire
    created_at TIMESTAMP,
    modified_at TIMESTAMP
);

CREATE TABLE INDIVIDUS (
    individu_id SERIAL PRIMARY KEY,
    menage_id INT REFERENCES MENAGES(menage_id),
    numero_ordre INT,
    nom VARCHAR(255),
    prenom VARCHAR(255),
    sexe CHAR(1),
    age INT,
    lien_cm VARCHAR(50),
    -- ... autres colonnes
    created_at TIMESTAMP
);
```

## Process de Breakout

### Configuration dans l'Interface Admin

1. **Créer l'organisation**

   ```
   Code: ANSD
   Nom: ANSD Sénégal
   ```

2. **Ajouter connexion PostgreSQL**

   ```
   Nom: POSTGRES_RECENSEMENT
   Driver: pdo_pgsql
   Host: postgres
   Database: ansd_recensement
   User: ansd_user
   Password: ******
   Par défaut: OUI
   ```

3. **Créer/Importer dictionnaire**

   ```
   Nom: RECENSEMENT_2024
   Organisation: ANSD
   Connexion Breakout: POSTGRES_RECENSEMENT
   ```

4. **Configurer le breakout**
   ```
   Fréquence: Automatique (cron)
   Records inclus: TOUS ou sélection
   Items inclus: TOUS ou sélection
   ```

### Exécution Manuelle du Breakout

```bash
# Via commande Symfony (à créer)
php bin/console csweb:breakout:run RECENSEMENT_2024

# Ou via interface web
http://localhost/admin/dictionaries/RECENSEMENT_2024/breakout
```

## Sécurité

### Isolation des Connexions

Chaque organisation ne voit QUE ses connexions :

```php
// Dans DatabaseConnectionManager.php
public function getConnectionForDictionary(
    string $dictionaryName,
    int $organizationId  // ◄── FILTRAGE PAR ORG
) {
    $stm = "SELECT conn.*
            FROM cspro_dictionaries dict
            JOIN cspro_organization_db_connections conn
              ON dict.db_connection_id = conn.id
            WHERE dict.dictionary_name = :dictName
              AND dict.organization_id = :orgId";  // ◄── ISOLATION
}
```

### Chiffrement des Credentials

Tous les mots de passe sont chiffrés avec AES-256-CBC :

```php
// Stockage
$encrypted = $connectionManager->encryptPassword($plainPassword);
$connection->setDbPasswordEncrypted($encrypted);

// Utilisation
$plainPassword = $connectionManager->decryptPassword($encrypted);
$pdo = new PDO($dsn, $user, $plainPassword);
```

## Résumé

| Aspect                   | Configuration                       |
| ------------------------ | ----------------------------------- |
| **Base MySQL CSWeb**     | Imposée par CSPro - Ne change pas   |
| **Stockage initial**     | BLOBs compressés dans MySQL         |
| **Choix de destination** | PostgreSQL / MySQL / SQL Server     |
| **Par organisation**     | Chaque org choisit son SGBD         |
| **Données finales**      | Tables tabulaires exploitables      |
| **Sécurité**             | Isolation + chiffrement credentials |

---

**Prochaine étape** : [Installation](02-INSTALLATION-GUIDE.md)
