# Guide de Gestion des Organisations - CSWeb Multi-Tenant

## Concept Multi-Tenant

Dans CSWeb Multi-Tenant, chaque **organisation** (structure, institut) est isolée et ne voit que :
- Ses propres enquêtes/projets
- Ses propres dictionnaires
- Ses propres données
- Ses propres utilisateurs

## Commandes de Gestion

### 1. Créer une Nouvelle Organisation

```bash
php bin/console csweb:org:create \
  --code=<CODE_UNIQUE> \
  --name="<NOM_COMPLET>" \
  [--type=<TYPE>] \
  [--country=<PAYS>] \
  [--email=<EMAIL>]
```

**Exemples :**

```bash
# ANSD Sénégal
php bin/console csweb:org:create \
  --code=ANSD \
  --name="Agence Nationale de la Statistique et de la Démographie" \
  --type=statistics_office \
  --country=SN \
  --email=contact@ansd.sn

# INS Bénin
php bin/console csweb:org:create \
  --code=INS_BENIN \
  --name="Institut National de la Statistique du Bénin" \
  --type=statistics_office \
  --country=BJ \
  --email=contact@insae.bj

# Cabinet de collecte
php bin/console csweb:org:create \
  --code=DATACORP \
  --name="DataCorp Survey Solutions" \
  --type=survey_firm \
  --country=SN \
  --email=info@datacorp.sn
```

**Types d'organisations disponibles :**
- `statistics_office` : Institut national de statistique
- `research` : Centre de recherche/université
- `survey_firm` : Cabinet de collecte de données
- `ngo` : ONG
- `government` : Agence gouvernementale
- `other` : Autre

### 2. Lister les Organisations

```bash
# Via SQL
php bin/console doctrine:query:sql \
  "SELECT id, organization_code, organization_name, is_active
   FROM cspro_organizations ORDER BY organization_name"

# Ou créer une commande custom
php bin/console csweb:org:list
```

### 3. Activer/Désactiver une Organisation

```bash
# Désactiver
php bin/console doctrine:query:sql \
  "UPDATE cspro_organizations SET is_active = 0 WHERE organization_code = 'ANSD'"

# Réactiver
php bin/console doctrine:query:sql \
  "UPDATE cspro_organizations SET is_active = 1 WHERE organization_code = 'ANSD'"
```

## Gestion des Connexions de Base de Données

### 1. Ajouter une Connexion PostgreSQL

```bash
php bin/console csweb:db:add \
  --org=ANSD \
  --name="POSTGRES_RECENSEMENT" \
  --driver=pdo_pgsql \
  --host=postgres \
  --port=5432 \
  --db=ansd_recensement \
  --user=ansd_user \
  --password=secure_password \
  --default \
  --test
```

### 2. Ajouter une Connexion MySQL

```bash
php bin/console csweb:db:add \
  --org=ANSD \
  --name="MYSQL_ENQUETES" \
  --driver=pdo_mysql \
  --host=mysql \
  --port=3306 \
  --db=ansd_enquetes \
  --user=ansd_user \
  --password=secure_password \
  --charset=utf8mb4 \
  --test
```

### 3. Ajouter une Connexion SQL Server

```bash
php bin/console csweb:db:add \
  --org=ANSD \
  --name="SQLSERVER_DATAWAREHOUSE" \
  --driver=sqlsrv \
  --host=sqlserver \
  --port=1433 \
  --db=ansd_dw \
  --user=sa \
  --password=YourStrong!Password \
  --test
```

### 4. Lister les Connexions d'une Organisation

```bash
php bin/console doctrine:query:sql \
  "SELECT c.id, c.connection_name, c.db_driver, c.db_host, c.db_name, c.is_default
   FROM cspro_organization_db_connections c
   JOIN cspro_organizations o ON c.organization_id = o.id
   WHERE o.organization_code = 'ANSD'
   ORDER BY c.is_default DESC, c.connection_name"
```

### 5. Tester une Connexion

```bash
# Le flag --test est automatique lors de la création
# Pour tester une connexion existante, utiliser directement PHP

docker exec -it csweb_php php << 'EOF'
<?php
require '/var/www/html/vendor/autoload.php';

$kernel = new AppKernel('prod', false);
$kernel->boot();
$container = $kernel->getContainer();

$manager = $container->get('AppBundle\Service\DatabaseConnectionManager');
$em = $container->get('doctrine.orm.entity_manager');

$conn = $em->getRepository('AppBundle:DatabaseConnection')->find(1); // ID de la connexion
$result = $manager->testConnection($conn);

echo $result['success'] ? "✓ Success\n" : "✗ Failed: {$result['message']}\n";
EOF
```

## Scénarios d'Usage

### Scénario 1 : Institut avec Plusieurs Enquêtes

**Organisation :** ANSD Sénégal

**Configuration :**
- Connexion MySQL pour les petites enquêtes
- Connexion PostgreSQL pour le recensement (gros volumes)
- Connexion SQL Server pour l'entrepôt de données

```bash
# 1. Créer l'organisation
php bin/console csweb:org:create \
  --code=ANSD \
  --name="ANSD Sénégal" \
  --type=statistics_office \
  --country=SN

# 2. Ajouter connexion PostgreSQL (par défaut)
php bin/console csweb:db:add \
  --org=ANSD \
  --name="POSTGRES_RECENSEMENT" \
  --driver=pdo_pgsql \
  --host=postgres \
  --db=ansd_recensement \
  --user=ansd \
  --password=secret \
  --default

# 3. Ajouter connexion MySQL (enquêtes secondaires)
php bin/console csweb:db:add \
  --org=ANSD \
  --name="MYSQL_ENQUETES" \
  --driver=pdo_mysql \
  --host=mysql \
  --db=ansd_enquetes \
  --user=ansd \
  --password=secret

# 4. Ajouter connexion SQL Server (datawarehouse)
php bin/console csweb:db:add \
  --org=ANSD \
  --name="SQLSERVER_DW" \
  --driver=sqlsrv \
  --host=sqlserver \
  --db=ansd_dw \
  --user=sa \
  --password=secret
```

### Scénario 2 : Plusieurs Organisations sur Même Infrastructure

**Cas d'usage :** Hébergeur SaaS servant plusieurs instituts

```bash
# Organisation 1: ANSD
php bin/console csweb:org:create --code=ANSD --name="ANSD Sénégal"
php bin/console csweb:db:add --org=ANSD --name=POSTGRES_PROD \
  --driver=pdo_pgsql --host=postgres --db=ansd_data --user=ansd --password=pass1 --default

# Organisation 2: INS Bénin
php bin/console csweb:org:create --code=INS_BENIN --name="INS Bénin"
php bin/console csweb:db:add --org=INS_BENIN --name=POSTGRES_PROD \
  --driver=pdo_pgsql --host=postgres --db=ins_benin_data --user=ins_benin --password=pass2 --default

# Organisation 3: Université
php bin/console csweb:org:create --code=UCAD --name="Université Cheikh Anta Diop" --type=research
php bin/console csweb:db:add --org=UCAD --name=MYSQL_RECHERCHE \
  --driver=pdo_mysql --host=mysql --db=ucad_data --user=ucad --password=pass3 --default
```

**Résultat :** 3 organisations complètement isolées sur la même infrastructure Docker.

### Scénario 3 : Migration depuis CSWeb Classique

**Situation :** Vous avez déjà CSWeb avec des données existantes

```bash
# 1. Créer organisation "DEFAULT" pour rétrocompatibilité
php bin/console csweb:org:create \
  --code=DEFAULT \
  --name="Organisation par Défaut"

# 2. Connecter à l'ancienne base PostgreSQL
php bin/console csweb:db:add \
  --org=DEFAULT \
  --name="POSTGRES_LEGACY" \
  --driver=pdo_pgsql \
  --host=postgres \
  --db=cspro_data \
  --user=cspro \
  --password=ancien_password \
  --default

# 3. Les dictionnaires existants seront automatiquement migrés
```

## Isolation des Données

### Comment ça marche ?

Chaque organisation a son propre **espace isolé** :

```
Organisation ANSD
├── Dictionnaires ANSD
│   ├── RECENSEMENT_2024
│   └── ENQUETE_MENAGES
├── Connexion DB: POSTGRES_PROD
│   └── Base: ansd_data
└── Utilisateurs ANSD
    ├── admin@ansd.sn
    └── agent1@ansd.sn

Organisation INS_BENIN
├── Dictionnaires INS
│   ├── RGPH_2024
│   └── ENQUETE_AGRICOLE
├── Connexion DB: POSTGRES_PROD
│   └── Base: ins_benin_data
└── Utilisateurs INS
    ├── admin@insae.bj
    └── agent1@insae.bj
```

### Règles d'Isolation

1. **Dictionnaires** : Un dictionnaire appartient à UNE SEULE organisation
2. **Utilisateurs** : Un utilisateur ne voit que les données de son organisation
3. **Connexions DB** : Chaque organisation gère ses propres connexions
4. **Données** : Séparation physique dans des bases de données différentes

## Commandes Additionnelles Utiles

### Statistiques par Organisation

```bash
php bin/console doctrine:query:sql "
  SELECT
    o.organization_name,
    COUNT(DISTINCT d.id) as nb_dictionaries,
    COUNT(DISTINCT c.id) as nb_connections
  FROM cspro_organizations o
  LEFT JOIN cspro_dictionaries d ON d.organization_id = o.id
  LEFT JOIN cspro_organization_db_connections c ON c.organization_id = o.id
  WHERE o.is_active = 1
  GROUP BY o.id, o.organization_name
"
```

### Vérifier l'Isolation

```bash
# Compter les dictionnaires par organisation
php bin/console doctrine:query:sql "
  SELECT o.organization_code, COUNT(d.id) as dict_count
  FROM cspro_organizations o
  LEFT JOIN cspro_dictionaries d ON d.organization_id = o.id
  GROUP BY o.id, o.organization_code
"
```

## Bonnes Pratiques

1. **Codes Organisation** : Utiliser des codes courts, uniques et parlants (ex: ANSD, INS_BENIN)
2. **Noms de Connexion** : Préfixer avec le type de SGBD (ex: POSTGRES_PROD, MYSQL_DEV)
3. **Connexion par Défaut** : Toujours définir une connexion par défaut par organisation
4. **Tests** : Toujours tester les connexions avec `--test`
5. **Sécurité** : Utiliser des mots de passe forts et uniques par connexion

## Dépannage

### Organisation ne peut pas se connecter à la DB

```bash
# 1. Vérifier que la connexion existe
php bin/console doctrine:query:sql \
  "SELECT * FROM cspro_organization_db_connections
   WHERE organization_id = (SELECT id FROM cspro_organizations WHERE organization_code = 'ANSD')"

# 2. Tester la connexion manuellement
docker exec -it csweb_postgres psql -U <user> -d <dbname>
```

### Changer la Connexion par Défaut

```bash
# Désactiver l'ancienne
php bin/console doctrine:query:sql \
  "UPDATE cspro_organization_db_connections
   SET is_default = 0
   WHERE id = <old_connection_id>"

# Activer la nouvelle
php bin/console doctrine:query:sql \
  "UPDATE cspro_organization_db_connections
   SET is_default = 1
   WHERE id = <new_connection_id>"
```

---

**Prochaine étape** : [Configuration des Dictionnaires](04-DICTIONARY-SETUP.md)
