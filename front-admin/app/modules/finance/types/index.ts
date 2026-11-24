import type { ExpenseStatusValue } from '../services/financeService'

export type ExpenseStatus = ExpenseStatusValue

export interface AccountRevenue {
  accountLabel: string
  retired: boolean
  orders: number
  grossCents: number
  refundedCents: number
  feesCents: number
  netCents: number
  estimatedFees: boolean
}

export interface BudgetLine {
  id: string
  name: string
  budgetCents: number | null
  committedCents: number
  paidCents: number
  remainingCents: number | null
  percentUsed: number | null
  overBudget: boolean
}

export interface Summary {
  revenueCents: number
  ticketsGrossCents: number
  ticketsRefundedCents: number
  ticketsFeesCents: number
  ticketsNetCents: number
  sponsorsReceivedCents: number
  sponsorsOutstandingCents: number
  accounts: AccountRevenue[]
  paidCents: number
  pendingCents: number
  overdueCount: number
  balanceCents: number
  forecastCents: number
  budget: BudgetLine[]
  budgetedCents: number
  committedCents: number
}

export interface Category { id: string, name: string, budgetCents: number | null }

export interface Expense {
  id: string
  description: string
  categoryId: string
  categoryName: string
  supplierId: string | null
  supplierName: string | null
  amountCents: number
  dueOn: string | null
  paidOn: string | null
  status: ExpenseStatus
  statusLabel: string
  overdue: boolean
  notes: string | null
  receipt: { name: string, sizeBytes: number } | null
}

export interface ExpenseTotals { count: number, amountCents: number, paidCents: number, pendingCents: number }

export interface SupplierOption { id: string, name: string }

export interface ExpenseInput {
  categoryId: string
  supplierId: string
  description: string
  amountCents: number
  dueOn: string
  paidOn: string
  status: ExpenseStatus
  notes: string
}

export type Failure = { ok: false, message: string, fieldErrors: Record<string, string> }

export type MutationResult = { ok: true } | Failure
