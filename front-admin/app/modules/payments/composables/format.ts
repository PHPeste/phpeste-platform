const dateTime = new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })

/** "10 de jun., 09:00". */
export const formatDateTime = (iso: string | null) => (iso ? dateTime.format(new Date(iso)) : '')

/** Só os dígitos de um CPF ou CNPJ digitado com pontuação. */
export const digits = (text: string) => text.replace(/\D/g, '')
