# CSWeb Pro - Modernisation de CSWeb avec Configuration Flexible

## Contexte du Projet

Je suis développeur senior full-stack avec 7+ ans d'expérience, contractant pour l'ANSD (Agence Nationale de la Statistique et de la Démographie) au Sénégal. J'ai utilisé CSWeb en production pour :

- Recensement national de la population (plusieurs millions d'enregistrements)
- Enquêtes de rebasing (données économiques)
- Multiples enquêtes statistiques nationales

## Référence de Base : CSWeb Docker

**Repository GitHub de référence :** https://github.com/csprousers/docker-csweb

Ce repository contient une dockerisation de CSWeb 8 (Symfony 5.4, PHP 8, MySQL). C'est notre point de départ pour comprendre l'architecture actuelle.

## Mission Principale

**Créer CSWeb Pro** : Une version modernisée, flexible et hautement configurable de CSWeb, tout en maintenant la compatibilité avec l'écosystème CSPro (CSEntry, dictionnaires .dcf, protocole de synchronisation).

**IMPORTANT :** Pour le moment, **PAS DE CODE**. Je veux d'abord :

1. Comprendre l'architecture actuelle de CSWeb 8
2. Définir l'architecture cible de CSWeb Pro
3. Planifier la stratégie de migration/modernisation
4. Établir un plan d'action détaillé

Le code viendra plus tard, une fois l'architecture validée.

## Problématiques Actuelles de CSWeb (à résoudre)

### 1. **Rigidité de l'Architecture Données**

- **Problème :** 1 DICTIONNAIRE CSPro (.dcf) = 1 base de données MySQL (imposé)
- **Impact :**
  - Impossibilité de regrouper plusieurs enquêtes dans une même base
  - Impossibilité de choisir le SGBD (MySQL imposé, or beaucoup de gouvernements africains standardisent sur SQL Server)
  - Multiplication des bases de données = gestion complexe et coûteuse

**Exemple concret ANSD :**
"Pour le recensement, on avait plusieurs questionnaires (ménages, bâtiments, décès, naissances). Chaque questionnaire = 1 base MySQL. Pour faire des analyses croisées, il fallait ensuite faire des ETL complexes pour regrouper les données."

**Solution souhaitée :**

- Permettre de configurer librement la base de données destinataire pour chaque DICT
- Support multi-SGBD : MySQL, PostgreSQL, SQL Server
- Permettre à plusieurs DICT de partager la même base de données
- Configuration via interface admin intuitive

### 2. **Synchronisation Rigide**

- **Problème :** Synchronisation gérée uniquement par CRON système
- **Impact :**
  - Tous les DICT se synchronisent en même temps (surcharge serveur)
  - Impossible de prioriser certaines enquêtes urgentes
  - Pas de flexibilité par enquête

**Exemple concret :**
"Pendant le recensement, entre 18h-20h, tous les agents synchronisent après leur journée. Le serveur était surchargé. On voulait étaler les sync, mais impossible sans modifier le code source."

**Solution souhaitée :**

- Configurer l'intervalle de synchronisation par DICT (ex: toutes les 5 min, 30 min, 1h, etc.)
- Système de queue intelligent pour gérer les pics de charge
- Possibilité de forcer une sync manuelle immédiate
- Monitoring temps réel des synchronisations

### 3. **Interface Utilisateur Datée**

- **Problème :** Design des années 2010, non responsive
- **Impact :**
  - Image peu professionnelle pour présentations gouvernementales
  - Difficile à utiliser sur tablette/mobile pour superviseurs terrain
  - Pas de customisation (logo, couleurs, branding)

**Solution souhaitée :**

- **Design moderne avec Tailwind CSS** (IMPÉRATIF)
- Interface responsive (desktop, tablette, mobile)
- Thèmes customisables par organisation
- Dashboard analytics temps réel avec visualisations
- **ATTENTION : Utiliser UNIQUEMENT Tailwind CSS pour le design, sans casser l'architecture existante**

### 4. **Monitoring Limité**

- **Problème :** Tableaux de bord basiques, pas d'alertes
- **Impact :**
  - Difficile de superviser des opérations à grande échelle (recensements)
  - Découverte tardive des problèmes (agents qui ne synchronisent pas, erreurs)
  - Pas de métriques de qualité des données

**Solution souhaitée :**

- Dashboard monitoring temps réel
- Alertes configurables (email, webhook)
- Métriques de qualité des données
- Logs d'audit complets
- Statistiques de progression par enquête

### 5. **Absence de Multi-tenancy**

- **Problème :** Une instance = une organisation
- **Impact :**
  - Impossible pour un hébergeur de servir plusieurs instituts
  - Impossibilité d'offrir du SaaS
  - Coût élevé pour chaque petit institut

**Solution souhaitée :**

- Multi-tenancy complet avec isolation des données
- Gestion des organisations avec leurs propres configurations
- White-labeling (chaque organisation peut avoir son branding)

### 6. **Exports et Interopérabilité**

- **Problème :** Exports basiques (CSV uniquement)
- **Impact :**
  - Pas de support SDMX (standard statistique international)
  - Pas d'API moderne pour intégration
  - Travail manuel pour exporter vers SPSS, Stata

**Solution souhaitée :**

- Export multi-formats : SDMX 3.0, CSV, JSON, XML, SPSS, Stata, Excel
- API REST moderne et documentée (OpenAPI/Swagger)
- Webhooks pour intégration avec d'autres systèmes

### 7. **Déploiement et Maintenance**

- **Problème :** Installation manuelle complexe
- **Impact :**
  - Nécessite des compétences IT pointues
  - Déploiement long et sujet à erreurs
  - Difficile de scaler (ajouter des serveurs)

**Solution souhaitée :**

- Docker Compose pour déploiement simplifié
- Docker Swarm pour production (haute disponibilité)
- Scripts d'installation automatisés
- Documentation claire et complète

## Architecture Technique Actuelle (CSWeb 8)

D'après le repository https://github.com/csprousers/docker-csweb :

```json
{
  "backend": "Symfony 5.4",
  "php": "8.0+",
  "database": "MySQL uniquement",
  "frontend": "Twig templates",
  "deployment": "Docker (basique)"
}
```

**Composants clés à comprendre :**

- Parser de dictionnaires CSPro (.dcf)
- Protocole de synchronisation avec CSEntry
- Gestion des case assignments (affectation des questionnaires aux agents)
- Système d'authentification

## Stack Technique Proposée pour CSWeb Pro

### Backend : Symfony 7.x (continuité)

**Justification :**

- CSWeb 8 utilise déjà Symfony 5.4 → Migration facilitée
- Doctrine DBAL supporte nativement MySQL, PostgreSQL, SQL Server
- Symfony Messenger pour les queues asynchrones
- API Platform pour REST API moderne
- Écosystème mature et professionnel

### Frontend : Choix à Valider

**Option A : Rester avec Twig + Tailwind CSS**

- **Avantages :**
  - Migration progressive depuis Symfony/Twig existant
  - Moins de complexité (pas de séparation backend/frontend)
  - Symfony UX Components (Stimulus, Turbo)
  - **Tailwind CSS s'intègre parfaitement avec Twig**
- **Inconvénients :**
  - Moins moderne pour dashboard complexes
  - Interactivité limitée comparé à React/Vue

**Option B : Next.js 15 + TypeScript + Tailwind CSS (Ma préférence)**

- **Avantages :**
  - Découplage backend/frontend (API REST)
  - Performance optimale (SSR, ISR)
  - Expérience développeur moderne
  - UI components réutilisables (shadcn/ui avec Tailwind)
  - **Tailwind CSS est natif dans Next.js**
- **Inconvénients :**
  - Complexité accrue (2 projets séparés)
  - Plus de temps de développement initial

**Option C : Inertia.js + Vue/React + Tailwind CSS**

- **Avantages :**
  - Garde le routing Symfony
  - Utilise React/Vue pour l'UI
  - Pas besoin d'API REST
  - **Tailwind CSS fonctionne parfaitement**
- **Inconvénients :**
  - Moins de séparation qu'avec Next.js
  - Communauté plus petite

### Bases de Données : Multi-SGBD

- **PostgreSQL 15+** (recommandé pour base principale)
- **MySQL 8+** (compatibilité CSWeb classique)
- **SQL Server 2019+** (environnements Microsoft/gouvernements)
- Doctrine DBAL gère l'abstraction complète

### Infrastructure

- **Redis** : Cache, sessions, queues
- **Nginx** : Reverse proxy
- **Docker & Docker Compose** : Conteneurisation
- **Docker Swarm** : Orchestration production (haute disponibilité)

### Design System

- **Tailwind CSS** (OBLIGATOIRE pour tout le design)
- **shadcn/ui** (si Next.js/React) ou équivalent Tailwind
- **Recharts/D3.js** pour visualisations (avec Tailwind)
- Design responsive, moderne, professionnel

## Mon Expérience Technique Pertinente

### SEDAS (Système d'Échange de Données Statistiques) - ANSD

- Plateforme nationale de gestion et dissémination de données statistiques
- **Stack :** Spring Boot + Next.js 15 + PostgreSQL
- Conformité SDMX 3.0
- **Design :** Tailwind CSS + shadcn/ui
- Utilisé en production à l'ANSD

### ZIARRA360

- Plateforme de gestion de pèlerinages
- **Stack :** Docker Swarm orchestration
- Haute disponibilité et scalabilité

### Expertise

- **Frontend :** Next.js 15, React, TypeScript, **Tailwind CSS expert**
- **Backend :** Spring Boot, Symfony, PHP moderne
- **Bases de données :** PostgreSQL expert, MySQL avancé, SQL Server
- **DevOps :** Docker, Docker Swarm, CI/CD
- **Standards :** SDMX 3.0, REST API, OpenAPI
- **Environnement :** Mac M1 Pro

## Questions Stratégiques à Discuter AVANT le Code

### 1. Architecture Frontend

**Question critique :** Quel frontend choisir pour CSWeb Pro ?

**Critères de décision :**

- **Compatibilité avec CSWeb 8** : Peut-on migrer progressivement ?
- **Time to market** : Quel sera le plus rapide à développer ?
- **Maintenabilité** : Quelle solution sera la plus facile à maintenir 10+ ans ?
- **Talent pool** : Quelle stack sera la plus facile à recruter en Afrique ?
- **Performance** : Dashboard temps réel avec milliers d'enregistrements
- **Design avec Tailwind** : Quelle option permet la meilleure intégration de Tailwind CSS ?

**Ma préférence :** Next.js 15 + Tailwind CSS (expérience SEDAS positive)
**Mais je suis ouvert :** Si Twig + Tailwind permet une migration plus rapide, je suis preneur

### 2. Migration depuis CSWeb 8

**Questions :**

- **Big Bang vs Progressif** : Tout réécrire ou migrer module par module ?
- **Compatibilité** : Doit-on garantir une compatibilité 100% avec CSWeb 8 ?
- **Données existantes** : Comment migrer les données d'instances CSWeb 8 existantes ?
- **Cohabitation** : CSWeb Pro peut-il cohabiter avec CSWeb 8 pendant une transition ?

### 3. Priorisation des Features

**Question :** Quel MVP pour valider le concept ?

**Options de MVP :**

**MVP Minimal (3 mois) :**

- Multi-base de données (MySQL, PostgreSQL, SQL Server)
- Configuration par DICT
- Interface admin moderne avec **Tailwind CSS**
- Compatibilité sync CSEntry
- Docker setup

**MVP Medium (4-5 mois) :**

- MVP Minimal +
- Multi-tenancy
- Monitoring basique
- Sync configurable par DICT
- Export CSV/Excel

**MVP Complet (6-7 mois) :**

- MVP Medium +
- Dashboard avancé avec **Tailwind CSS**
- Alertes configurables
- Export SDMX/SPSS/Stata
- API REST documentée

**Recommandation attendue :** Quel MVP pour mon contexte (bootstrap, solo, besoin de références rapides) ?

### 4. Stratégie de Développement

**Questions :**

- **Solo vs Équipe** : Combien de temps en solo ? Quand recruter ?
- **Open Source dès le début** : Publier sur GitHub dès le départ ou attendre le MVP ?
- **Communauté** : Comment impliquer la communauté CSPro ?
- **Tests** : Quel niveau de tests pour le MVP ?

### 5. Architecture Multi-Base de Données

**Questions techniques :**

- **Connection Pooling** : Comment gérer efficacement des dizaines de connexions DB ?
- **Migrations** : Comment gérer les migrations de schéma pour des DB dynamiques ?
- **Performance** : Stratégies de cache pour éviter de trop solliciter les DB ?
- **Sécurité** : Comment stocker et chiffrer les credentials des DB externes ?

### 6. Design System avec Tailwind CSS

**Questions :**

- **Tailwind Config** : Configuration Tailwind optimale pour un admin dashboard ?
- **Components** : Créer notre propre library ou utiliser shadcn/ui (si React) ?
- **Thèmes** : Comment permettre la customisation par organisation tout en gardant Tailwind ?
- **Dark Mode** : Priorité ou pas pour le MVP ?
- **Responsive** : Breakpoints Tailwind à privilégier pour tablettes terrain ?

## Livrables Attendus (AVANT le code)

### 1. Analyse de l'Architecture CSWeb 8

- Comprendre le code existant (via le repo GitHub)
- Identifier les composants critiques (parser .dcf, sync protocol, etc.)
- Évaluer ce qui peut être réutilisé vs réécrit
- Identifier les limitations techniques actuelles

### 2. Architecture Cible CSWeb Pro

- **Diagramme d'architecture** complet (backend, frontend, DB, infra)
- **Schéma de base de données** détaillé (avec multi-tenancy)
- **Stack technique** justifiée avec recommandations
- **Stratégie frontend** avec comparaison des options (Twig vs Next.js vs Inertia)
- **Design system Tailwind CSS** : structure et conventions

### 3. Stratégie Multi-Base de Données

- Architecture de `ConnectionManager`
- Gestion du connection pooling
- Stratégie de migration de schémas
- Sécurité des credentials
- Exemples d'implémentation conceptuelle (pas de code complet)

### 4. Plan de Migration depuis CSWeb 8

- Stratégie de migration (Big Bang vs Progressif)
- Script de migration des données
- Plan de compatibilité avec CSEntry
- Checklist de validation

### 5. Roadmap Produit

- **Phase 1 (MVP)** : Fonctionnalités et timeline
- **Phase 2 (Avancé)** : Fonctionnalités et timeline
- **Phase 3 (Enterprise)** : Fonctionnalités et timeline
- Estimations réalistes (développement solo)

### 6. Design System et UI/UX

- **Guide d'utilisation de Tailwind CSS** pour CSWeb Pro
- Palette de couleurs et typographie
- Composants UI prioritaires (avec références Tailwind)
- Wireframes ou mockups des vues principales (textuel acceptable)
- Standards de responsive design

### 7. Documentation Technique

- **Architecture Decision Records (ADR)** pour les choix clés
- Guide de contribution (pour futurs contributeurs)
- Standards de code et conventions
- Structure de la documentation

### 8. Plan d'Exécution

- **Prochaines étapes concrètes** (dans l'ordre)
- Critères de succès par phase
- Risques identifiés et mitigation
- Ressources nécessaires (temps, outils, éventuels recrutements)

## Structure Attendue de la Documentation

```
docs/
├── 01-ANALYSIS.md                    # Analyse CSWeb 8 actuel
├── 02-ARCHITECTURE.md                # Architecture cible détaillée
├── 03-DATABASE-STRATEGY.md           # Stratégie multi-DB
├── 04-FRONTEND-DECISION.md           # Choix frontend justifié
├── 05-DESIGN-SYSTEM.md               # Design system Tailwind CSS
├── 06-MIGRATION-PLAN.md              # Plan de migration
├── 07-ROADMAP.md                     # Roadmap produit
├── 08-TECHNICAL-SPECIFICATIONS.md    # Specs techniques
├── 09-API-DESIGN.md                  # Design de l'API REST
└── 10-EXECUTION-PLAN.md              # Plan d'exécution détaillé
```

## Contraintes et Contexte Important

### Contexte de Développement

- **Développeur solo** (pour l'instant)
- **Bootstrap** (pas de budget initial significatif)
- **Mac M1 Pro** (environnement de dev)
- **Expérience terrain** avec CSWeb en production
- **Réseau** : Contact direct ANSD, réseau AFRISTAT

### Contraintes Techniques

- **Compatibilité CSEntry** obligatoire (protocole de sync)
- **Performance** : Doit gérer des millions d'enregistrements
- **Sécurité** : Données gouvernementales sensibles
- **Scalabilité** : Du petit institut (100 agents) au recensement national (5000+ agents)
- **Design moderne** : **Tailwind CSS obligatoire**, aucun framework CSS alternatif

### Contraintes Business

- **Time to market** : Besoin de références clients rapidement (6-12 mois)
- **Maintenance long terme** : Recensements = cycles de 10 ans
- **Marché cible** : Instituts statistiques africains (budget limité)
- **Différenciation** : Doit apporter une vraie valeur vs CSWeb gratuit

## Questions Spécifiques pour Claude Code

### Questions d'Architecture

1. **Frontend** : Twig+Tailwind vs Next.js+Tailwind vs Inertia+Tailwind ? Quelle est la meilleure option pour mon contexte (solo, bootstrap, besoin de références rapides) ?
2. **Multi-DB** : Quelle architecture pour gérer proprement des dizaines de connexions dynamiques ?
3. **Queues** : Symfony Messenger suffit ou faut-il RabbitMQ pour des recensements ?
4. **Monitoring** : Comment implémenter un dashboard temps réel performant avec Tailwind ?

### Questions de Stratégie

5. **MVP** : Quel périmètre de MVP pour valider le concept en 3-4 mois ?
6. **Migration** : Stratégie Big Bang ou progressive depuis CSWeb 8 ?
7. **Open Source** : Publier dès le début ou attendre le MVP ?
8. **Communauté** : Comment impliquer la communauté CSPro utilisateurs ?

### Questions de Design avec Tailwind CSS

9. **Tailwind Config** : Configuration recommandée pour un admin dashboard moderne ?
10. **Components** : Créer une library custom ou utiliser shadcn/ui (si React) ?
11. **Customisation** : Comment permettre le white-labeling avec Tailwind (thèmes par organisation) ?
12. **Performance** : Best practices Tailwind pour un dashboard avec beaucoup de données ?

### Questions Pratiques

13. **Timeline** : Estimation réaliste pour le MVP en solo ?
14. **Risques** : Quels sont les risques majeurs et comment les mitiger ?
15. **Recrutement** : Quand et qui recruter en premier ?
16. **Infrastructure** : Docker Compose suffit pour démarrer ou passer direct à Swarm ?

## Approche Souhaitée de Claude Code

**Ton :** Architecte logiciel senior avec expérience en modernisation de legacy systems

**Méthodologie :**

1. **Comprendre d'abord** : Analyser le repo CSWeb 8 existant
2. **Proposer ensuite** : Architecture cible avec justifications
3. **Challenger mes hypothèses** : Si une de mes idées est mauvaise, dis-le
4. **Pragmatisme** : Solutions adaptées à mon contexte (solo, bootstrap, marché africain)
5. **Focus Tailwind** : Toutes les recommandations UI/UX doivent être compatibles Tailwind CSS

**Format de réponse attendu :**

- Structured (sections claires avec headers Markdown)
- Justifications pour chaque recommandation
- Alternatives présentées avec pros/cons
- Exemples concrets (architecture, pas de code complet)
- Diagrammes en Mermaid si pertinent
- Références à des projets similaires si applicable

**Ce que je NE veux PAS :**

- Code complet maintenant (on fera ça plus tard)
- Recommandations génériques ("ça dépend...")
- Solutions trop complexes pour un dev solo
- Frameworks CSS autres que Tailwind

## Contexte Additionnel sur CSPro

**CSPro (Census and Survey Processing System) :**

- Développé par US Census Bureau
- Utilisé dans 100+ pays pour recensements et enquêtes
- Écosystème complet : Designer, Entry, Web, DataViewer
- Format de dictionnaire : .dcf (fichier texte structuré)
- Protocole de sync : HTTP/HTTPS avec authentification

**Utilisateurs typiques :**

- Instituts Nationaux de Statistique
- Organisations internationales (UNFPA, Banque Mondiale)
- Universités et centres de recherche
- Cabinets de collecte de données

**Cas d'usage principaux :**

- Recensements de population (tous les 10 ans)
- Enquêtes démographiques et de santé (DHS)
- Enquêtes agricoles
- Enquêtes économiques (entreprises)
- Enquêtes de satisfaction

## Let's Start! 🚀

**Commence par :**

1. **Analyser le repository** https://github.com/csprousers/docker-csweb

   - Structure du code
   - Composants clés
   - Points d'extension possibles

2. **Proposer l'architecture cible** de CSWeb Pro

   - Recommandation frontend (Twig vs Next.js vs Inertia) avec focus Tailwind CSS
   - Architecture backend (Symfony 7)
   - Stratégie multi-base de données
   - Design system Tailwind CSS

3. **Plan de migration** depuis CSWeb 8

4. **Roadmap produit** avec phases et estimations

5. **Répondre aux questions critiques** listées ci-dessus

**Prends ton temps**, c'est une analyse stratégique importante. Je préfère une analyse approfondie maintenant qu'un code précipité plus tard.

Si tu as besoin de clarifications sur des aspects métier (comment fonctionne un recensement, etc.), n'hésite pas à demander.

---

**Note finale :** Je vais partager ce prompt avec Claude Code. L'objectif est d'avoir une vision claire et un plan d'action solide AVANT d'écrire la première ligne de code. Le succès de CSWeb Pro dépend des bonnes décisions d'architecture maintenant.
