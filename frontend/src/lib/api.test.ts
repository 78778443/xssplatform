import { describe, it, expect, vi, beforeEach } from 'vitest'
import { api, ApiError } from './api'

describe('api client', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
  })

  it('builds action url with query and sends json body', async () => {
    const fetchMock = vi
      .spyOn(globalThis, 'fetch')
      .mockImplementation(async () => new Response(JSON.stringify({ ok: true, data: { id: 1 } }), { status: 200 }))
    const data = await api<{ id: number }>('project.get', { query: { id: '1' } })
    expect(data.id).toBe(1)
    expect(fetchMock).toHaveBeenCalledWith(
      '/api.php?action=project.get&id=1',
      expect.objectContaining({ credentials: 'same-origin' }),
    )

    fetchMock.mockClear()
    await api('project.create', { method: 'POST', body: { title: 'x' }, csrf: 'tok' })
    const [, init] = fetchMock.mock.calls[0]
    expect((init as RequestInit).headers).toMatchObject({ 'X-CSRF-Token': 'tok' })
    expect((init as RequestInit).body).toBe(JSON.stringify({ title: 'x' }))
  })

  it('throws ApiError with code and status', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(
        JSON.stringify({ ok: false, error: { code: 'operation_failed', message: '操作失败' } }),
        { status: 419 },
      ),
    )
    await expect(api('logout', { method: 'POST' })).rejects.toMatchObject({
      code: 'operation_failed',
      status: 419,
    })
    await expect(api('logout', { method: 'POST' })).rejects.toBeInstanceOf(ApiError)
  })
})
