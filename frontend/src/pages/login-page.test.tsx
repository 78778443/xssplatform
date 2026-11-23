import { describe, it, expect, vi } from 'vitest'
import { screen, fireEvent, waitFor } from '@testing-library/react'
import { renderWithProviders } from '../test/render'
import { LoginPage } from './login-page'
import * as apiModule from '../lib/api'

vi.mock('../lib/api', () => ({
  api: vi.fn(),
  ApiError: class ApiError extends Error {
    code = ''
    status = 400
  },
}))

describe('LoginPage', () => {
  it('submits credentials and the auto-login flag', async () => {
    (apiModule.api as unknown as ReturnType<typeof vi.fn>).mockResolvedValue({
      user: { id: 1, userName: 'admin', adminLevel: 1, token: 't' },
      csrf: 't',
    })
    renderWithProviders(<LoginPage />)
    fireEvent.change(screen.getByLabelText(/用户名或邮箱/i), { target: { value: 'admin' } })
    fireEvent.change(screen.getByLabelText(/密码/i), { target: { value: 'pw123456' } })
    fireEvent.click(screen.getByRole('checkbox'))
    fireEvent.click(screen.getByRole('button', { name: /登录/i }))
    await waitFor(() => {
      expect(apiModule.api).toHaveBeenCalledWith(
        'login',
        expect.objectContaining({
          method: 'POST',
          body: expect.objectContaining({ user: 'admin', pwd: 'pw123456', auto: 1 }),
        }),
      )
    })
  })

  it('shows the server error message', async () => {
    (apiModule.api as unknown as ReturnType<typeof vi.fn>).mockRejectedValue(
      Object.assign(new Error('登录失败,请检查用户/邮箱或密码'), { code: 'login_failed', status: 400 }),
    )
    renderWithProviders(<LoginPage />)
    fireEvent.change(screen.getByLabelText(/用户名或邮箱/i), { target: { value: 'admin' } })
    fireEvent.change(screen.getByLabelText(/密码/i), { target: { value: 'wrong' } })
    fireEvent.click(screen.getByRole('button', { name: /登录/i }))
    await waitFor(() => {
      expect(screen.getByText('登录失败,请检查用户/邮箱或密码')).toBeTruthy()
    })
  })
})
