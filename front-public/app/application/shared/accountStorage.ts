export const ACCOUNT_STORAGE_PREFIX = 'phpeste.account.'

export function forgetAccountData() {
  try {
    const keys = Array.from({ length: localStorage.length }, (_, index) => localStorage.key(index))
    keys.filter((key): key is string => key?.startsWith(ACCOUNT_STORAGE_PREFIX) ?? false).forEach((key) => localStorage.removeItem(key))
  } catch {
    return
  }
}
