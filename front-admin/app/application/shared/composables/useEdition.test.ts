import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it } from 'vitest'
import { useEdition } from './useEdition'

beforeEach(() => {
  setActivePinia(createPinia())
})

describe('useEdition', () => {
  it('começa sem edição atual', () => {
    const { current } = useEdition()

    expect(current.value).toBeNull()
  })

  it('guarda e limpa a edição atual', () => {
    const { current, setCurrent, clear } = useEdition()

    setCurrent({ id: 1, slug: 'phpeste-2027', name: 'PHPeste 2027' })
    expect(current.value).toEqual({ id: 1, slug: 'phpeste-2027', name: 'PHPeste 2027' })

    clear()
    expect(current.value).toBeNull()
  })
})
