const SDK_URL = 'https://sdk.mercadopago.com/js/v2'

export interface MpField { mount: (containerId: string) => MpField, on: (event: string, handler: (data: { bin?: string }) => void) => MpField, unmount: () => void }

export interface MpInstallments { payment_method_id: string, issuer: { id: string | number }, payer_costs: Array<{ installments: number, installment_amount: number, total_amount: number, recommended_message: string }> }

export interface MercadoPagoInstance {
  fields: {
    create: (type: 'cardNumber' | 'expirationDate' | 'securityCode', options?: Record<string, unknown>) => MpField
    createCardToken: (data: { cardholderName: string, identificationType: string, identificationNumber: string }) => Promise<{ id: string }>
  }
  getInstallments: (data: { amount: string, bin: string, paymentTypeId?: string }) => Promise<MpInstallments[]>
}

type MercadoPagoConstructor = new (publicKey: string, options?: { locale?: string }) => MercadoPagoInstance

declare global {
  interface Window { MercadoPago?: MercadoPagoConstructor }
}

let loading: Promise<MercadoPagoConstructor> | null = null

/** Carrega o SDK do Mercado Pago uma vez. Os campos do cartão vivem em iframes do próprio Mercado Pago. */
export function loadMercadoPago(): Promise<MercadoPagoConstructor> {
  if (window.MercadoPago) return Promise.resolve(window.MercadoPago)
  loading ??= new Promise<MercadoPagoConstructor>((resolve, reject) => {
    const script = document.createElement('script')
    script.src = SDK_URL
    script.async = true
    script.onload = () => (window.MercadoPago ? resolve(window.MercadoPago) : reject(new Error('Mercado Pago SDK missing')))
    script.onerror = () => reject(new Error('Mercado Pago SDK failed'))
    document.head.appendChild(script)
  }).catch((error: unknown) => {
    loading = null
    throw error
  })
  return loading
}
