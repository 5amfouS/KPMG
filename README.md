# KPMG
# Plateforme de Gestion des Entreprises et Employés

## Objectif

Ce projet est une plateforme web destinée aux services RH (ressources humaines) pour gérer :
- les entreprises,
- leurs employés,
- et l’envoi sécurisé des fiches de paie.

Deux types d’utilisateurs peuvent accéder au système :  
- Les **RH**, qui gèrent les entreprises et les employés.  
- Les **Admins**, qui gèrent les comptes RH et la conformité du site.

---

## Utilisateurs & Rôles

| Rôle      | Fonctionnalités |
|-----------|-----------------|
| **RH**    | - Créer, modifier, supprimer des entreprises  
|            |- Gérer les employés  
|           |  - Envoyer des fiches de paie sécurisées (PDF protégé par mot de passe, envoyé par SMS) |
| **Admin** | - Gérer les utilisateurs RH  
|           |- Envoyer des fiches de paie sécurisées (PDF protégé par mot de passe, envoyé par SMS) |
|           | - Surveiller la conformité du système |

---

## 🧱 Structure de la Base de Données

### `entreprise`
- `id` *(int)* : Identifiant unique
- `nom` *(string)* : Nom de l’entreprise

### `employe`
- `id` *(int)* : Identifiant unique
- `cin` *(string)* : Numéro d’identité
- `nom` *(string)* : Nom complet
- `email` *(string)* : Adresse email
- `entreprise_id` *(FK)* : Référence vers l’entreprise

### `user`
- `id` *(int)* : Identifiant
- `email` *(string)* : Adresse email
- `mot_de_passe` *(string)* : Mot de passe chiffré
- `role` *(enum)* : `ROLE_RH` ou `ROLE_ADMIN`

---

## 🔐 Sécurité des fiches de paie

- Les fiches de paie sont générées au format **PDF sécurisé**.
- Le fichier PDF est **protégé par mot de passe** : le mot de passe est le **CIN de l’employé**.
- Le mot de passe est **envoyé automatiquement par SMS** via un modem connecté au serveur (Gammu).
- L’employé reçoit :
  - le **fichier PDF par email**,
  - le **mot de passe par SMS**.

---

## 📦 Structure des fichiers PDF

Les fiches de paie sont organisées dans un fichier ZIP selon cette structure :

paie.zip/
└── paie/
└── {nom_entreprise}/
└── {numero_employe}/
└── fiche_de_paie_modele.pdf


---

## ⚙️ Stack Technique

- **Back-end** : Symfony (PHP)
- **Base de données** : MySQL
- **Génération PDF** : Dompdf
- **Envoi SMS** : Gammu + Modem 4G (port COM)
- **Importation des employés** : via fichier Excel (.xlsx)
- **Front-end** : HTML5 / CSS3 avec templates Twig

---

## 📲 Fonctionnalités Clés

- ✅ Interface RH ergonomique
- ✅ Envoi sécurisé des fiches de paie
- ✅ Gestion complète des entreprises et employés
- ✅ Interface administrateur pour la supervision
- ✅ Importation par lot des employés via Excel

---

## 🚀 Scénario d’utilisation

1. Le RH se connecte à la plateforme.
2. Il crée une entreprise.
3. Il ajoute les employés via formulaire ou fichier Excel.
4. Il sélectionne les fiches de paie à envoyer.
5. Chaque PDF est protégé et envoyé par mail.
6. Le mot de passe est envoyé par SMS à chaque employé.

---

## 📌 Améliorations futures

- 📎 Signature électronique des PDF
- 📊 Statistiques RH et journal d’envoi
- 📱 Version mobile (PWA ou app native)
- 🔁 Ré-envoi automatique en cas d’échec de transmission
- ☁️ Intégration stockage cloud (Google Drive / Dropbox)

---

## 🧑‍💻 Auteur

Projet réalisé par **Sami Ben Abdelkader**.  
Contact : [smail.benabdelkader@gmail.com](mailto:smail.benabdelkader@gmail.com)

---

