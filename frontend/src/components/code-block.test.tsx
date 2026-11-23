import { describe, it, expect, vi } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import { CodeBlock } from './code-block'

describe('CodeBlock', () => {
  it('renders text verbatim and copies it', async () => {
    const writeText = vi.fn().mockResolvedValue(undefined)
    Object.assign(navigator, { clipboard: { writeText } })
    render(<CodeBlock label="项目代码" value={'<script src="x"></script>'} />)
    expect(screen.getByText('项目代码')).toBeTruthy()
    expect(screen.getByText('<script src="x"></script>')).toBeTruthy()
    fireEvent.click(screen.getByRole('button', { name: /复制/i }))
    expect(writeText).toHaveBeenCalledWith('<script src="x"></script>')
  })
})
