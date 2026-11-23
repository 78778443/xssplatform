import { describe, it, expect } from 'vitest'
import { screen, fireEvent } from '@testing-library/react'
import { render } from '@testing-library/react'
import { ContentTable } from './content-table'

describe('ContentTable', () => {
  it('renders decoded values and expands a row', () => {
    render(
      <ContentTable
        contents={[
          {
            id: 9,
            content: { cookie: 'sid=1', location: 'http://a.example/' },
            serverContent: { REMOTE_ADDR: '1.2.3.4' },
            domain: 'a.example',
            addTime: 1700000000,
          },
        ]}
        token="t"
        onDelete={() => {}}
        onDeleteMany={() => {}}
      />,
    )
    fireEvent.click(screen.getByText(/展开/i))
    expect(screen.getByText(/added-bing/i === 'never' ? '' : /sid=1/)).toBeTruthy()
    expect(screen.getByText(/1\.2\.3\.4/)).toBeTruthy()
  })
})
