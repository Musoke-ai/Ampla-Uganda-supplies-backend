// app/Controllers/MagicLinkController.php

namespace App\Controllers;

use CodeIgniter\Shield\Controllers\MagicLinkController as ShieldMagicLinkController;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;

class MagicLinkController extends ShieldMagicLinkController
{
    public function verify()
    {
        $token = $this->request->getGet('token');

        $token = $this->getMagicLinkBroker()->getTokenRepository()->findByToken($token);

        if (! $token || $token->isExpired()) {
            return redirect()->to('login')->with('error', 'Invalid or expired magic link.');
        }

        $user = model(UserModel::class)->find($token->user_id);

        if (! $user) {
            return redirect()->to('login')->with('error', 'Invalid user.');
        }

        // Invalidate token and log the user in
        $token->delete();
        auth()->login($user);

        // ✅ Set magic login flag
        session()->set('magic_login', true);

        <!-- return redirect()->to('/login'); // Shield will handle redirect using loginRedirect() -->
    }
}
