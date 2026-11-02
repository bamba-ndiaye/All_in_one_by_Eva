# All in One by Eva

Application web développée avec Laravel dans le cadre d'un projet d'équipe.

## Technologies utilisées

- **Backend :** PHP et Laravel
- **Base de données :** MySQL
- **Frontend :** Blade, JavaScript et Tailwind CSS
- **Gestion des dépendances PHP :** Composer
- **Gestion des dépendances JavaScript :** npm et Vite
- **Versioning :** Git et GitHub

## Prérequis

Avant de commencer, installez :

- PHP et ses extensions nécessaires à Laravel
- Composer
- MySQL
- Node.js et npm
- Git

## Installation du projet

### 1. Récupérer le projet

```bash
git clone URL_DU_DEPOT
cd All_in_one_by_Eva
```

Remplacez `URL_DU_DEPOT` par l'adresse GitHub du projet.

### 2. Installer les dépendances PHP

```bash
composer install
```

### 3. Configurer l'environnement

```bash
cp .env.example .env
```

Générez la clé de l'application :

```bash
php artisan key:generate
```

### 4. Configurer MySQL

Créez une base de données nommée :

```text
all_in_one_eva
```

Configurez ensuite les informations de connexion dans le fichier `.env` :

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=all_in_one_eva
DB_USERNAME=root
DB_PASSWORD=
```

Adaptez le nom d'utilisateur et le mot de passe selon votre installation MySQL.

### 5. Exécuter les migrations

```bash
php artisan migrate
```

### 6. Installer les dépendances frontend

```bash
npm install
```

### 7. Lancer le projet

Dans un premier terminal :

```bash
php artisan serve
```

Dans un deuxième terminal :

```bash
npm run dev
```

Ouvrez ensuite votre navigateur à l'adresse :

http://localhost:8000

## Travail en équipe

Le projet est développé en équipe avec Git et GitHub.

Les fonctionnalités sont organisées en différentes branches selon les tâches et les sprints.

Avant de commencer à travailler, pensez à récupérer les dernières modifications :

```bash
git pull origin main
```

Créez ensuite votre branche de travail :

```bash
git switch -c nom-de-votre-branche
```

Après vos modifications, utilisez une Pull Request pour proposer leur intégration dans la branche principale.

## Commandes Laravel utiles

| Commande | Rôle |
|---|---|
| `php artisan serve` | Démarrer le serveur Laravel |
| `php artisan migrate` | Exécuter les migrations |
| `php artisan route:list` | Afficher les routes |
| `php artisan make:controller NomController` | Créer un contrôleur |
| `php artisan make:model NomModel` | Créer un modèle |
| `php artisan optimize:clear` | Vider les caches Laravel |

## Licence

Projet développé dans le cadre d'un travail d'équipe.