import login from '../../../modules/login/locales/pt-br'
import githubLogin from '../../../modules/githubLogin/locales/pt-br'
import forgotPassword from '../../../modules/forgotPassword/locales/pt-br'
import resetPassword from '../../../modules/resetPassword/locales/pt-br'
import invitation from '../../../modules/invitation/locales/pt-br'
import communities from '../../../modules/communities/locales/pt-br'
import overview from '../../../modules/overview/locales/pt-br'
import identity from '../../../modules/identity/locales/pt-br'
import ui from './ui'
import shell from '../../../modules/shell/locales/pt-br'

// Every module's locale is registered here — see docs/ARCHITECTURE.md,
// "Registro obrigatório". Add one import + one key per new module.
export default {
  login,
  githubLogin,
  forgotPassword,
  resetPassword,
  invitation,
  communities,
  overview,
  identity,
  shell,
  ui,
}
