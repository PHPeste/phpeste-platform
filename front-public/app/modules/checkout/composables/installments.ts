/** Valor de cada parcela sem juros, arredondado para cima, para mostrar antes do gateway. */
export function evenInstallments(totalCents: number, max: number) {
  return Array.from({ length: Math.max(1, max) }, (_, index) => {
    const installments = index + 1
    return { installments, amountCents: Math.ceil(totalCents / installments) }
  })
}
