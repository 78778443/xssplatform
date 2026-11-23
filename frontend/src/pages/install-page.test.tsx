import { describe, it, expect, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import { InstallPage } from './install-page'
import * as apiModule from '../lib/api'

vi.mock('../lib/api', () => ({
  api: vi.fn(),
  ApiError: class ApiError extends Error {
    code = ''
    status = 400
  },
}))

describe('InstallPage', () => {
  it('disables the agreement when config.php is not writable', async () => {
    (apiModule.api as unknown as ReturnType<typeof vi.fn>).mockResolvedValue({
      installed: false,
      configWritable: false,
      phpVersion: '8.3.6',
      extensions: { mysqli: true, curl: true, openssl: true },
    })
    render(<InstallPage />)
    await waitFor(() => expect(screen.getByRole('button', { name: /我同意此协议/i })).toBeTruthy())
    expect((screen.getByRole('button', { name: /我同意此协议/i }) as HTMLButtonElement).disabled).toBe(true)
    expect(apiModule.api).toHaveBeenCalledWith('install.status')
  })
})
