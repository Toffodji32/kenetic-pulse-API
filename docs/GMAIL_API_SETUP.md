# Gmail API OAuth Setup

La solution SMTP/Gmail App Password est bloquée sur Render (port 587/465 bloqués).

On utilise l'API REST Gmail via OAuth (HTTPS, port 443) → **aucun port SMTP requis**, **pas de domaine nécessaire**.

## Étapes

### 1. Créer un projet Google Cloud

1. Va sur https://console.cloud.google.com/
2. Connecte-toi avec ton compte Gmail
3. Clique sur le sélecteur de projet (en haut à gauche) → **Nouveau projet**
4. Nomme-le (ex: `kinetic-pulse-mail`) → **Créer**

### 2. Activer la Gmail API

1. Dans le projet, va sur https://console.cloud.google.com/apis/library/gmail.googleapis.com
2. Clique sur **Activer**

### 3. Créer des credentials OAuth Desktop

1. Va sur https://console.cloud.google.com/apis/credentials
2. Clique sur **+ Créer des identifiants** → **ID client OAuth**
3. Si demandé, configure l'écran de consentement :
   - **User Type** : Externe → **Créer**
   - **App name** : `Kinetic Pulse Mail`
   - **User support email** : ton email
   - **Developer contact** : ton email
   - **Scope** : ajoute `https://www.googleapis.com/auth/gmail.send`
   - **Test users** : ajoute ton email Gmail
   - **Enregistrer**
4. **Type d'application** : **Application de bureau**
5. **Nom** : `Gmail API Client`
6. **Créer** → télécharge le JSON (contient `client_id` et `client_secret`)

### 4. Obtenir un refresh token (OAuth flow)

Tu dois exécuter un script localement UNE SEULE fois pour obtenir le refresh token.

#### Option A : Script PHP simple

Crée un fichier `get_gmail_token.php` localement (hors du projet) :

```php
<?php
// get_gmail_token.php
// Remplace par tes valeurs du JSON téléchargé
$clientId     = 'ton_client_id.apps.googleusercontent.com';
$clientSecret = 'ton_client_secret';
$redirectUri  = 'urn:ietf:wg:oauth:2.0:oob'; // Code-based redirect

$scope = 'https://www.googleapis.com/auth/gmail.send';

// Étape 1 : Ouvrir l'URL d'autorisation
$authUrl = 'https://accounts.google.com/o/oauth2/auth?'
    . http_build_query([
        'client_id'     => $clientId,
        'redirect_uri'  => $redirectUri,
        'response_type' => 'code',
        'scope'         => $scope,
        'access_type'   => 'offline',     // Nécessaire pour obtenir refresh_token
        'prompt'        => 'consent',     // Force l'affichage du refresh_token
    ]);

echo "1. Ouvre cette URL dans un navigateur :\n$authUrl\n\n";
echo "2. Connecte-toi avec ton Gmail, autorise, puis copie le code affiché.\n\n";
$code = readline('3. Colle le code ici : ');

// Étape 2 : Échanger le code contre un access_token + refresh_token
$tokenUrl = 'https://oauth2.googleapis.com/token';
$data = http_build_query([
    'code'          => $code,
    'client_id'     => $clientId,
    'client_secret' => $clientSecret,
    'redirect_uri'  => $redirectUri,
    'grant_type'    => 'authorization_code',
]);

$ctx = stream_context_create([
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content' => $data,
    ],
]);

$result = file_get_contents($tokenUrl, false, $ctx);
$tokens = json_decode($result, true);

echo "\nRésultat :\n";
print_r($tokens);

if (isset($tokens['refresh_token'])) {
    echo "\n✅ REFRESH_TOKEN : " . $tokens['refresh_token'] . "\n";
}
```

Exécute :
```bash
php get_gmail_token.php
```

#### Option B : Utiliser l'outil en ligne Google OAuth Playground

1. Va sur https://developers.google.com/oauthplayground
2. Clique sur l'engrenage (⚙) → coche **Use your own OAuth credentials**
3. Entre ton `client_id` et `client_secret`
4. Dans la liste de gauche, cherche **Gmail API v1** → coche `https://www.googleapis.com/auth/gmail.send`
5. Clique sur **Authorize APIs**
6. Connecte-toi avec ton Gmail → **Allow**
7. Clique sur **Exchange authorization code for tokens**
8. Copie le **Refresh token**

### 5. Configurer Render

1. Va sur https://dashboard.render.com
2. Ouvre ton service **kenetic-pulse-api**
3. **Environment** → **Environment Variables**
4. Ajoute :

| Variable | Valeur |
|---|---|
| `MAILER_DSN` | `gmail+api://default` |
| `GOOGLE_CLIENT_ID` | `ton_client_id.apps.googleusercontent.com` |
| `GOOGLE_CLIENT_SECRET` | `ton_client_secret` |
| `GOOGLE_REFRESH_TOKEN` | `ton_refresh_token` |

5. **Save Changes** → Render redéploie automatiquement

### 6. Tester

1. Va sur https://kinetic-pulse-phi.vercel.app
2. Crée un client
3. Vérifie les logs Render pour voir si l'email est envoyé

## Dépannage

- **403 Forbidden** : le refresh token n'a pas le scope `gmail.send`. Recommence l'étape 4 en t'assurant d'inclure `https://www.googleapis.com/auth/gmail.send`.
- **400 invalid_grant** : le refresh token a expiré ou a été révoqué. Recommence l'étape 4.
- **404 Not Found** : Render déploie encore. Attends 2-3 min.
- Consulte les logs Render : Dashboard → ton service → **Logs**.
