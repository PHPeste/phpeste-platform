import { startMockApi } from './mockApi'

export default async function globalSetup() {
  const server = await startMockApi()
  return () => new Promise<void>((resolve) => server.close(() => resolve()))
}
