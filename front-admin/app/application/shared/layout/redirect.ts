export const HOME_AFTER_LOGIN = '/'

/**
 * Só aceita caminho interno absoluto; URL completa, "//evil.com" e truques
 * com barra invertida ou espaço caem na página inicial em vez de virar
 * redirecionamento aberto.
 */
export function safeRedirect(value: unknown): string {
  if (typeof value === 'string' && value.startsWith('/') && !value.startsWith('//') && !/[\\\s]/.test(value)) {
    return value
  }
  return HOME_AFTER_LOGIN
}
